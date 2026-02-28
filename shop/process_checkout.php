<?php
session_start();
require_once '../config/connectdbuser.php';

// ==========================================
// 1. ตรวจสอบล็อกอินและเตรียมข้อมูล Navbar
// ==========================================
if (!isset($_SESSION['u_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$u_id = $_SESSION['u_id'];
$profileImage = "https://ui-avatars.com/api/?name=User&background=F43F85&color=fff";
$totalCartItems = 0;

$sqlUser = "SELECT u_username, u_image FROM `user` RIGHT JOIN `account` ON user.u_id = account.u_id WHERE account.u_id = ?";
if ($stmtUser = mysqli_prepare($conn, $sqlUser)) {
    mysqli_stmt_bind_param($stmtUser, "i", $u_id);
    mysqli_stmt_execute($stmtUser);
    $resUser = mysqli_stmt_get_result($stmtUser);
    if ($rowUser = mysqli_fetch_assoc($resUser)) {
        if (!empty($rowUser['u_image']) && file_exists("../uploads/" . $rowUser['u_image'])) {
            $profileImage = "../uploads/" . $rowUser['u_image'];
        } else {
            $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($rowUser['u_username']) . "&background=F43F85&color=fff";
        }
    }
    mysqli_stmt_close($stmtUser);
}

// ==========================================
// 2. ดึงข้อมูลตะกร้าสินค้า & คำนวณราคาใหม่
// ==========================================
$cartItems = [];
$subtotal = 0;

$sqlCart = "SELECT c.cart_id, c.quantity, p.p_id, p.p_name, p.p_price FROM `cart` c JOIN `product` p ON c.p_id = p.p_id WHERE c.u_id = ?";
if ($stmtCart = mysqli_prepare($conn, $sqlCart)) {
    mysqli_stmt_bind_param($stmtCart, "i", $u_id);
    mysqli_stmt_execute($stmtCart);
    $resultCart = mysqli_stmt_get_result($stmtCart);
    while ($rowCart = mysqli_fetch_assoc($resultCart)) {
        $cartItems[] = $rowCart;
        $subtotal += ($rowCart['p_price'] * $rowCart['quantity']);
        $totalCartItems += $rowCart['quantity'];
    }
    mysqli_stmt_close($stmtCart);
}

// ==========================================
// 3. รับค่าจากหน้า checkout.php
// ==========================================
// ถ้ามีการส่งข้อมูลมาจากหน้า checkout สดๆ ร้อนๆ ให้รีเซ็ตสถานะเก่าทิ้ง เพื่อรับออเดอร์ใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shipping_address'])) {
    unset($_SESSION['order_saved']);
    unset($_SESSION['last_order_no']);
    
    $_SESSION['checkout_data'] = [
        'shipping_address' => $_POST['shipping_address'],
        'shipping_method' => $_POST['shipping_method'] ?? 'standard',
        'payment_method' => $_POST['payment_method'] ?? 'promptpay'
    ];
}

$checkoutData = $_SESSION['checkout_data'] ?? null;
if (!$checkoutData || count($cartItems) == 0) {
    if (!isset($_SESSION['order_saved'])) {
        header("Location: cart.php");
        exit();
    }
}

// 🟢 คำนวณราคาสุทธิ (กฎครบ 1,000 ส่งฟรี/ด่วนลด 50)
$isFreeShippingEligible = ($subtotal >= 1000);
if ($checkoutData['shipping_method'] == 'express') {
    $shippingCost = $isFreeShippingEligible ? 50 : 100;
} else {
    $shippingCost = $isFreeShippingEligible ? 0 : 50;
}
$netTotal = $subtotal + $shippingCost;

$payment_status = 'pending'; 
$error_msg = "";
$should_save_order = false;
$slip_name = null;

// ==========================================
// 4. ลอจิกการตรวจสอบและบันทึกข้อมูล
// ==========================================
// เช็คว่าเคยเซฟออเดอร์นี้ไปแล้วหรือยังในการโหลดหน้านี้ครั้งก่อนหน้า
if (isset($_SESSION['order_saved']) && $_SESSION['order_saved'] === true) {
    $payment_status = 'success';
    $orderNo = $_SESSION['last_order_no'] ?? 'N/A';
    $totalCartItems = 0; // ตะกร้าว่างแล้ว
} else {
    // ก. ถ้าเป็นแบบ บัตรเครดิต หรือ เก็บเงินปลายทาง -> เซฟออเดอร์ได้เลย
    if (in_array($checkoutData['payment_method'], ['credit_card', 'cod'])) {
        $should_save_order = true;
    } 
    // ข. ถ้าเป็นแบบ โอนเงิน/PromptPay -> ต้องรอรับสลิปโอนเงินก่อน
    elseif ($checkoutData['payment_method'] === 'promptpay') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'upload_slip') {
            if (isset($_FILES['slip_image']) && $_FILES['slip_image']['error'] == 0) {
                $uploadDir = '../uploads/slips/';
                if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
                
                $ext = pathinfo($_FILES['slip_image']['name'], PATHINFO_EXTENSION);
                $slip_name = "slip_" . time() . "_" . $u_id . "." . $ext;
                
                if(move_uploaded_file($_FILES['slip_image']['tmp_name'], $uploadDir . $slip_name)){
                    $should_save_order = true; // อัปโหลดผ่าน พร้อมเซฟออเดอร์
                } else {
                    $payment_status = 'failed';
                    $error_msg = "ระบบขัดข้อง: ไม่สามารถบันทึกไฟล์รูปภาพสลิปได้";
                }
            } else {
                $payment_status = 'failed';
                $error_msg = "กรุณาแนบไฟล์สลิปหลักฐานการโอนเงิน เพื่อยืนยันการสั่งซื้อ";
            }
        } else {
            // ยังไม่ได้กดแนบสลิป ให้อยู่หน้า pending รอโอนเงิน
            $payment_status = 'pending';
        }
    }

    // ==========================================
    // 5. รันคำสั่งบันทึกลง Database
    // ==========================================
    if ($should_save_order) {
        $orderNo = "ORD" . date('Ymd') . rand(1000, 9999);
        $status = ($checkoutData['payment_method'] == 'cod') ? 'pending' : 'processing';
        $pm = $checkoutData['payment_method'];
        $sm = $checkoutData['shipping_method'];
        
        // 1. บันทึกออเดอร์หลัก (เพิ่มคอลัมน์ช่องทางชำระเงิน, จัดส่ง, สลิป)
        $sqlInsertOrder = "INSERT INTO `orders` (order_no, u_id, total_amount, status, payment_method, shipping_method, slip_image) VALUES (?, ?, ?, ?, ?, ?, ?)";
        if ($stmtOrder = mysqli_prepare($conn, $sqlInsertOrder)) {
            mysqli_stmt_bind_param($stmtOrder, "sidsiss", $orderNo, $u_id, $netTotal, $status, $pm, $sm, $slip_name);
            
            if(mysqli_stmt_execute($stmtOrder)) {
                $newOrderId = mysqli_insert_id($conn);
                
                // 2. บันทึกสินค้าในตะกร้า และ ตัดสต๊อก (แก้ชื่อคอลัมน์เป็น price และ p_image ให้ตรงกับตาราง order_items)
                foreach ($cartItems as $item) {
                    $img = $item['p_image'] ?? '';
                    $sqlInsertItem = "INSERT INTO `order_items` (order_id, p_id, p_name, p_image, price, quantity) VALUES (?, ?, ?, ?, ?, ?)";
                    if ($stmtItem = mysqli_prepare($conn, $sqlInsertItem)) {
                        // iisdsi = int, int, string, string, double, int
                        mysqli_stmt_bind_param($stmtItem, "iisdsi", $newOrderId, $item['p_id'], $item['p_name'], $img, $item['p_price'], $item['quantity']);
                        mysqli_stmt_execute($stmtItem);
                        mysqli_stmt_close($stmtItem);
                    }
                    
                    // อัปเดตตัดสต๊อก
                    $sqlUpdateStock = "UPDATE `product` SET p_stock = p_stock - ? WHERE p_id = ?";
                    if ($stmtStock = mysqli_prepare($conn, $sqlUpdateStock)) {
                        mysqli_stmt_bind_param($stmtStock, "ii", $item['quantity'], $item['p_id']);
                        mysqli_stmt_execute($stmtStock);
                        mysqli_stmt_close($stmtStock);
                    }
                }
                
                // 3. ลบของออกจากตะกร้า
                $sqlClearCart = "DELETE FROM `cart` WHERE u_id = ?";
                if ($stmtClear = mysqli_prepare($conn, $sqlClearCart)) {
                    mysqli_stmt_bind_param($stmtClear, "i", $u_id);
                    mysqli_stmt_execute($stmtClear);
                    mysqli_stmt_close($stmtClear);
                }
                
                // มาร์คสถานะว่าสำเร็จแล้ว
                $_SESSION['order_saved'] = true;
                $_SESSION['last_order_no'] = $orderNo;
                $payment_status = 'success';
                $totalCartItems = 0;
                
            } else {
                $payment_status = 'failed';
                $error_msg = "เกิดข้อผิดพลาดฐานข้อมูล (orders): " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmtOrder);
        } else {
            $payment_status = 'failed';
            $error_msg = "คำสั่ง SQL ผิดพลาด: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>สถานะการชำระเงิน - Lumina Beauty</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>

<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#ec2d88", 
                    "background-light": "#fff5f9",
                    "background-dark": "#1F1B24",
                    "text-main": "#1f2937",
                },
                fontFamily: {
                    display: ["Prompt", "sans-serif"],
                    sans: ["Prompt", "sans-serif"]
                },
                borderRadius: {"DEFAULT": "1rem", "lg": "1.5rem", "xl": "2rem", "full": "9999px"},
                boxShadow: {
                    "soft": "0 4px 20px -2px rgba(236, 45, 136, 0.15)",
                    "glow": "0 0 20px rgba(236, 45, 136, 0.4)"
                }
            },
        },
    }
</script>
<style>
    body { font-family: 'Prompt', sans-serif; }
    .glass-panel { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(236, 45, 136, 0.1); }
    .dark .glass-panel { background: rgba(31, 27, 36, 0.85); border-bottom: 1px solid rgba(255,255,255,0.05); }
    .cloud-gradient {
        background: radial-gradient(circle at 10% 20%, rgba(236, 45, 136, 0.05) 0%, transparent 30%),
                    radial-gradient(circle at 90% 80%, rgba(14, 165, 233, 0.05) 0%, transparent 30%);
    }
    
    .upload-area { border: 2px dashed #fce7f3; transition: all 0.3s; }
    .upload-area:hover, .upload-area.active { border-color: #ec2d88; background-color: #fff0f6; }
    .dark .upload-area { border-color: #4b5563; }
    .dark .upload-area:hover, .dark .upload-area.active { border-color: #ec2d88; background-color: rgba(236, 45, 136, 0.1); }
</style>
</head>
<body class="bg-background-light dark:bg-background-dark min-h-screen cloud-gradient font-sans text-text-main transition-colors duration-300">

<header class="sticky top-0 z-50 glass-panel shadow-sm px-6 py-4 mb-8 relative">
    <div class="w-full px-4 md:px-10 lg:px-16"> 
        <div class="flex justify-between items-center h-10 w-full">
            <a href="../home.php" class="flex items-center space-x-2 cursor-pointer hover:opacity-80 transition-opacity">
                <span class="material-icons-round text-primary text-4xl">spa</span>
                <span class="font-bold text-2xl tracking-tight text-primary font-display">Lumina</span>
            </a>
            
            <div class="hidden lg:flex gap-8 xl:gap-12 items-center justify-center flex-grow ml-10">
                <a class="group flex flex-col items-center justify-center transition" href="products.php">
                    <span class="text-[16px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary leading-tight">สินค้า</span>
                </a>
                <a class="group flex flex-col items-center justify-center transition" href="promotions.php">
                    <span class="text-[16px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary leading-tight">โปรโมชั่น</span>
                </a>
                <a class="group flex flex-col items-center justify-center transition" href="../contact.php">
                    <span class="text-[16px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary leading-tight">ติดต่อเรา</span>
                </a>
            </div>

            <div class="flex items-center space-x-2 sm:space-x-4">
                <a href="favorites.php" class="text-gray-500 dark:text-gray-300 hover:text-pink-600 transition relative flex items-center justify-center">
                    <span class="material-icons-round text-2xl">favorite_border</span>
                </a>
                <a href="cart.php" class="relative w-10 h-10 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-800 rounded-full transition-all cursor-pointer">
                    <span class="material-icons-round text-2xl">shopping_bag</span>
                    <span class="absolute -top-1.5 -right-2 bg-primary text-white text-[10px] font-bold rounded-full h-[18px] w-[18px] flex items-center justify-center border-2 border-white dark:border-gray-800"><?= $totalCartItems ?></span>
                </a>
                <button class="w-10 h-10 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-800 rounded-full transition-all" onclick="toggleTheme()">
                    <span class="material-icons-round dark:hidden text-2xl">dark_mode</span>
                    <span class="material-icons-round hidden dark:block text-yellow-400 text-2xl">light_mode</span>
                </button>
                
                <div class="relative group flex items-center">
                    <a href="../profile/account.php" class="block w-10 h-10 rounded-full bg-gradient-to-tr from-pink-300 to-purple-300 p-0.5 shadow-sm hover:shadow-md hover:scale-105 transition-all cursor-pointer">
                        <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-full h-full">
                            <img alt="Profile" class="w-full h-full rounded-full object-cover" src="<?= htmlspecialchars($profileImage) ?>" onerror="this.src='https://ui-avatars.com/api/?name=User&background=ec2d88&color=fff'"/>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
<main class="max-w-4xl mx-auto w-full px-4 pb-20 lg:px-8">
    
    <div class="mb-10 mt-4">
        <div class="flex items-center justify-between max-w-2xl mx-auto relative">
            <div class="absolute top-1/2 left-0 w-full h-1 bg-primary -z-10 -translate-y-1/2 rounded-full transition-all duration-500"></div>
            
            <div class="flex flex-col items-center gap-2">
                <div class="size-10 rounded-full bg-primary text-white flex items-center justify-center border-2 border-white shadow-sm">
                    <span class="material-icons-round text-lg">shopping_cart</span>
                </div>
            </div>
            <div class="flex flex-col items-center gap-2">
                <div class="size-10 rounded-full bg-primary text-white flex items-center justify-center border-2 border-white shadow-sm">
                    <span class="material-icons-round text-lg">receipt_long</span>
                </div>
            </div>
            <div class="flex flex-col items-center gap-2">
                <div class="size-12 rounded-full <?= $payment_status === 'failed' ? 'bg-red-500 border-red-100' : 'bg-primary border-pink-100 dark:border-gray-800' ?> text-white flex items-center justify-center border-4 shadow-glow">
                    <span class="material-icons-round text-xl"><?= $payment_status === 'failed' ? 'error_outline' : 'check_circle' ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($payment_status === 'pending'): ?>
    <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] shadow-soft border border-pink-50 dark:border-gray-700 p-8 md:p-12 text-center max-w-2xl mx-auto animate-[fadeIn_0.5s_ease-out]">
        <h1 class="text-3xl font-extrabold text-gray-800 dark:text-white mb-2">ชำระเงินผ่านการโอน</h1>
        <p class="text-gray-500 mb-8">กรุณาสแกน QR Code หรือโอนเงินเข้าบัญชีด้านล่างเพื่อชำระเงิน</p>

        <div class="bg-pink-50/50 dark:bg-gray-700/50 rounded-[2rem] p-6 sm:p-8 border border-pink-100 dark:border-gray-600 mb-8">
            <h2 class="text-2xl font-bold text-primary mb-6">ยอดชำระ: ฿<?= number_format($netTotal, 2) ?></h2>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-center">
                <div class="w-40 h-40 bg-white mx-auto rounded-2xl shadow-sm border border-gray-200 flex items-center justify-center p-2 relative">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/d/d0/QR_code_for_mobile_English_Wikipedia.svg" alt="PromptPay QR" class="w-full h-full object-contain opacity-80">
                    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-full p-1 shadow-sm border border-gray-100">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/e1/PromptPay_logo.svg/1024px-PromptPay_logo.svg.png" class="h-4">
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 shadow-sm text-left h-full flex flex-col justify-center border border-gray-100 dark:border-gray-600">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                            <span class="material-icons-round text-green-600 text-sm">account_balance</span>
                        </div>
                        <span class="font-bold text-gray-800 dark:text-white">ธนาคารกสิกรไทย</span>
                    </div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-lg font-mono text-gray-700 dark:text-gray-300 font-bold tracking-widest">123-4-56789-0</span>
                        <button type="button" class="text-primary hover:text-pink-600 transition-colors" onclick="navigator.clipboard.writeText('1234567890'); alert('คัดลอกเลขบัญชีแล้ว');"><span class="material-icons-round text-[20px]">content_copy</span></button>
                    </div>
                    <p class="text-xs text-gray-500">ชื่อบัญชี: บจก. ลูมิน่า บิวตี้</p>
                </div>
            </div>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" class="max-w-sm mx-auto">
            <input type="hidden" name="action" value="upload_slip">
            <label class="upload-area block w-full rounded-2xl cursor-pointer p-6 mb-6 group bg-white dark:bg-gray-800 relative overflow-hidden shadow-sm">
                <input type="file" name="slip_image" class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*" id="slipInput" onchange="previewFileName(this)">
                <div class="flex flex-col items-center gap-3">
                    <div class="w-14 h-14 bg-pink-50 dark:bg-gray-700 text-primary rounded-full flex items-center justify-center group-hover:scale-110 transition-transform shadow-sm">
                        <span class="material-icons-round text-3xl">cloud_upload</span>
                    </div>
                    <span class="font-bold text-gray-700 dark:text-gray-200 text-sm" id="fileNameText">คลิกเพื่ออัปโหลดสลิปโอนเงิน</span>
                    <span class="text-xs text-gray-400">รองรับไฟล์ JPG, PNG</span>
                </div>
            </label>

            <button type="submit" class="w-full bg-primary hover:bg-pink-600 text-white font-bold py-4 rounded-2xl shadow-lg shadow-primary/30 flex items-center justify-center gap-2 transition-transform transform hover:-translate-y-1">
                ยืนยันการชำระเงิน <span class="material-icons-round">arrow_forward</span>
            </button>
        </form>
    </div>
    
    <?php elseif ($payment_status === 'success'): ?>
    <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] shadow-soft border border-pink-50 dark:border-gray-700 p-10 md:p-16 text-center max-w-2xl mx-auto animate-[fadeIn_0.5s_ease-out]">
        <div class="w-28 h-28 bg-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm border-[8px] border-green-50 dark:border-gray-700">
            <span class="material-icons-round text-6xl">check</span>
        </div>
        <h1 class="text-3xl md:text-4xl font-extrabold text-gray-800 dark:text-white mb-3 tracking-tight">ชำระเงินสำเร็จ!</h1>
        <p class="text-gray-500 text-lg mb-8">ขอบคุณสำหรับการสั่งซื้อ หมายเลขออเดอร์ของคุณคือ <br><span class="font-bold text-primary text-xl tracking-wider mt-2 inline-block">#<?= $orderNo ?></span></p>

        <div class="bg-pink-50/50 dark:bg-gray-700/50 rounded-2xl p-6 mb-10 max-w-xs mx-auto border border-pink-100 dark:border-gray-600 shadow-inner">
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-2 font-medium">ระบบจะพากลับไปหน้าหลักอัตโนมัติใน</p>
            <div class="text-5xl font-display font-black text-primary animate-pulse" id="countdown">10</div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">วินาที</p>
        </div>

        <a href="../home.php" class="inline-flex items-center justify-center gap-2 px-10 py-4 bg-gray-900 hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100 text-white rounded-full font-bold transition-transform transform hover:-translate-y-1 shadow-lg text-lg">
            <span class="material-icons-round">home</span> กลับสู่หน้าหลักทันที
        </a>
    </div>
    <script>
        let timeLeft = 10;
        const countdownEl = document.getElementById('countdown');
        setInterval(() => {
            timeLeft--;
            if(countdownEl) countdownEl.innerText = timeLeft;
            if(timeLeft <= 0) window.location.href = '../home.php';
        }, 1000);
    </script>

    <?php elseif ($payment_status === 'failed'): ?>
    <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] shadow-soft border border-red-100 dark:border-red-900/30 p-10 md:p-16 text-center max-w-2xl mx-auto animate-[fadeIn_0.5s_ease-out]">
        <div class="w-28 h-28 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm border-[8px] border-red-50 dark:border-gray-700">
            <span class="material-icons-round text-6xl">close</span>
        </div>
        <h1 class="text-3xl md:text-4xl font-extrabold text-gray-800 dark:text-white mb-4 tracking-tight">การชำระเงินล้มเหลว</h1>
        
        <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-4 mb-8 inline-block max-w-md">
            <p class="text-red-500 text-sm font-medium leading-relaxed"><?= $error_msg ?></p>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="checkout.php" class="px-8 py-4 bg-primary hover:bg-pink-600 text-white rounded-full font-bold transition-transform transform hover:-translate-y-1 shadow-lg shadow-primary/30 flex items-center justify-center gap-2">
                <span class="material-icons-round">refresh</span> ลองใหม่อีกครั้ง
            </a>
            <a href="../home.php" class="px-8 py-4 bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600 rounded-full font-bold transition-colors flex items-center justify-center">
                กลับสู่หน้าหลัก
            </a>
        </div>
    </div>
    <?php endif; ?>

</main>

<script>
    if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
    
    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        localStorage.setItem('theme', htmlEl.classList.contains('dark') ? 'dark' : 'light');
    }

    // เปลี่ยนข้อความเมื่อเลือกไฟล์สลิป และทำให้กรอบเป็นสีชมพู
    function previewFileName(input) {
        const textSpan = document.getElementById('fileNameText');
        const uploadArea = input.closest('.upload-area');
        if (input.files && input.files[0]) {
            textSpan.textContent = "อัปโหลดแล้ว: " + input.files[0].name;
            textSpan.classList.add('text-primary');
            uploadArea.classList.add('active', 'bg-pink-50/50', 'border-primary');
            uploadArea.classList.remove('bg-white');
        } else {
            textSpan.textContent = "คลิกเพื่ออัปโหลดสลิปโอนเงิน";
            textSpan.classList.remove('text-primary');
            uploadArea.classList.remove('active', 'bg-pink-50/50', 'border-primary');
            uploadArea.classList.add('bg-white');
        }
    }
</script>
</body></html>