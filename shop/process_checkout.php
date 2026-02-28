<?php
session_start();
require_once '../config/connectdbuser.php';

// ==========================================
// 1. ตรวจสอบการล็อกอิน และดึงข้อมูลผู้ใช้ (สำหรับ Navbar)
// ==========================================
if (!isset($_SESSION['u_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$u_id = $_SESSION['u_id'];
$isLoggedIn = true;
$isAdmin = isset($_SESSION['admin_id']) ? true : false;
$userData = ['u_username' => 'ผู้ใช้งาน', 'u_email' => ''];
$profileImage = "https://ui-avatars.com/api/?name=User&background=ec2d88&color=fff";

$sqlUser = "SELECT a.u_username, a.u_email, u.u_image FROM `account` a LEFT JOIN `user` u ON a.u_id = u.u_id WHERE a.u_id = ?";
if ($stmtUser = mysqli_prepare($conn, $sqlUser)) {
    mysqli_stmt_bind_param($stmtUser, "i", $u_id);
    mysqli_stmt_execute($stmtUser);
    $resultUser = mysqli_stmt_get_result($stmtUser);
    if ($rowUser = mysqli_fetch_assoc($resultUser)) {
        $userData = $rowUser;
        if (!empty($rowUser['u_image']) && file_exists("../uploads/" . $rowUser['u_image'])) {
            $profileImage = "../uploads/" . $rowUser['u_image'];
        } else {
            $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($rowUser['u_username']) . "&background=ec2d88&color=fff";
        }
    }
    mysqli_stmt_close($stmtUser);
}

// ==========================================
// 2. ดึงข้อมูลตะกร้าเพื่อคำนวณยอด
// ==========================================
$cartItems = [];
$subtotal = 0;
$totalCartItems = 0;

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
// 3. จัดการสถานะการชำระเงิน
// ==========================================
$payment_status = 'pending'; // pending, success, failed
$error_msg = "";

// รับค่าจากหน้า checkout.php และเก็บลง Session ไว้ใช้ตอนบันทึก
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shipping_address'])) {
    $_SESSION['checkout_data'] = [
        'shipping_address' => $_POST['shipping_address'],
        'shipping_method' => $_POST['shipping_method'] ?? 'standard',
        'payment_method' => $_POST['payment_method'] ?? 'promptpay'
    ];
}

$checkoutData = $_SESSION['checkout_data'] ?? null;

if (!$checkoutData || count($cartItems) == 0) {
    header("Location: cart.php");
    exit();
}

// คำนวณยอดสุทธิ
$shippingCost = ($checkoutData['shipping_method'] == 'express') ? 100 : (($subtotal >= 500) ? 0 : 50);
$netTotal = $subtotal + $shippingCost;

// ถ้าเป็นบัตรเครดิต หรือ COD ให้ถือว่าสำเร็จทันที
if (isset($_POST['shipping_address']) && in_array($checkoutData['payment_method'], ['credit_card', 'cod'])) {
    $payment_status = 'success';
}

// ถ้ามีการกดปุ่มอัปโหลดสลิป
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'upload_slip') {
    if (isset($_FILES['slip_image']) && $_FILES['slip_image']['error'] == 0) {
        $payment_status = 'success';
        // ในระบบจริง จะต้อง move_uploaded_file ไปเก็บในโฟลเดอร์
    } else {
        $payment_status = 'failed';
        $error_msg = "ไม่พบไฟล์หลักฐานการโอนเงิน กรุณาแนบสลิปเพื่อยืนยันการสั่งซื้อ";
    }
}

// ==========================================
// 4. บันทึกออเดอร์ลงระบบ (ถ้าสถานะคือ success)
// ==========================================
$orderNo = "";
if ($payment_status === 'success' && !isset($_SESSION['order_saved'])) {
    $orderNo = "ORD" . date('Ymd') . rand(1000, 9999);
    $status = ($checkoutData['payment_method'] == 'cod') ? 'pending' : 'processing'; // ถ้า COD ให้เป็นรอชำระเงิน
    
    // 1. บันทึกลงตาราง orders
    $sqlInsertOrder = "INSERT INTO `orders` (order_no, u_id, total_amount, status) VALUES (?, ?, ?, ?)";
    if ($stmtOrder = mysqli_prepare($conn, $sqlInsertOrder)) {
        mysqli_stmt_bind_param($stmtOrder, "sids", $orderNo, $u_id, $netTotal, $status);
        if(mysqli_stmt_execute($stmtOrder)) {
            $newOrderId = mysqli_insert_id($conn);
            
            // 2. บันทึกลงตาราง order_items และตัดสต๊อก
            foreach ($cartItems as $item) {
                // บันทึกไอเทม
                $sqlInsertItem = "INSERT INTO `order_items` (order_id, p_id, p_name, p_price, quantity) VALUES (?, ?, ?, ?, ?)";
                if ($stmtItem = mysqli_prepare($conn, $sqlInsertItem)) {
                    mysqli_stmt_bind_param($stmtItem, "iisdi", $newOrderId, $item['p_id'], $item['p_name'], $item['p_price'], $item['quantity']);
                    mysqli_stmt_execute($stmtItem);
                    mysqli_stmt_close($stmtItem);
                }
                
                // ตัดสต๊อก
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
            
            $_SESSION['order_saved'] = true; // ป้องกันการรีเฟรชแล้วบันทึกซ้ำ
            $totalCartItems = 0; // อัปเดตตัวเลขใน Navbar ทันที
        }
        mysqli_stmt_close($stmtOrder);
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
                    "primary-light": "#fce7f3",
                    "background-light": "#fff5f9",
                    "background-dark": "#1F1B24",
                    "surface-white": "#ffffff",
                    "text-main": "#1f2937",
                    "text-muted": "#6b7280",
                    "accent-blue": "#e0f2fe",
                    "accent-pink": "#fce7f3",
                },
                fontFamily: {
                    display: ["Prompt", "sans-serif"],
                    sans: ["Prompt", "sans-serif"]
                },
                borderRadius: {"DEFAULT": "1rem", "lg": "1.5rem", "xl": "2rem", "full": "9999px"},
                boxShadow: {
                    "soft": "0 4px 20px -2px rgba(236, 45, 136, 0.1)",
                    "glow": "0 0 15px rgba(236, 45, 136, 0.3)"
                }
            },
        },
    }
</script>
<style>
    body { font-family: 'Prompt', sans-serif; }
    .glass-panel { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(236, 45, 136, 0.1); }
    .cloud-gradient {
        background: radial-gradient(circle at 10% 20%, rgba(236, 45, 136, 0.05) 0%, transparent 30%),
                    radial-gradient(circle at 90% 80%, rgba(14, 165, 233, 0.05) 0%, transparent 30%);
    }
    .upload-area { border: 2px dashed #fce7f3; transition: all 0.3s; }
    .upload-area:hover, .upload-area.active { border-color: #ec2d88; background-color: #fff0f6; }
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
                <div class="size-12 rounded-full <?= $payment_status === 'failed' ? 'bg-red-500 border-red-100' : 'bg-primary border-accent-pink' ?> text-white flex items-center justify-center border-4 shadow-glow">
                    <span class="material-icons-round text-xl"><?= $payment_status === 'failed' ? 'error_outline' : 'check_circle' ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($payment_status === 'pending'): ?>
    <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] shadow-soft border border-pink-50 dark:border-gray-700 p-8 md:p-12 text-center max-w-2xl mx-auto animate-[fadeIn_0.5s_ease-out]">
        <h1 class="text-3xl font-extrabold text-gray-800 dark:text-white mb-2">ชำระเงินผ่านการโอน</h1>
        <p class="text-gray-500 mb-8">กรุณาสแกน QR Code หรือโอนเงินเข้าบัญชีด้านล่างเพื่อชำระเงิน</p>

        <div class="bg-pink-50/50 dark:bg-gray-700/50 rounded-[2rem] p-8 border border-pink-100 dark:border-gray-600 mb-8">
            <h2 class="text-2xl font-bold text-primary mb-6">ยอดชำระ: ฿<?= number_format($netTotal, 2) ?></h2>
            
            <div class="w-48 h-48 bg-white mx-auto rounded-2xl shadow-sm border border-gray-200 flex items-center justify-center mb-6 p-2">
                <img src="../profile/qr.JPEG" alt="PromptPay QR" class="w-full h-full object-contain opacity-80">
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm text-left max-w-sm mx-auto">
                <div class="flex items-center gap-3 mb-2">
                    <span class="font-bold text-gray-800 dark:text-white">ธนาคารกรุงเทพ</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-lg font-mono text-gray-600 dark:text-gray-300 font-medium tracking-wider">414-425-3830</span>
                    <button class="text-primary text-sm font-bold hover:underline" onclick="navigator.clipboard.writeText('1234567890'); Swal.fire({toast:true, position:'top-end', icon:'success', title:'คัดลอกเลขบัญชีแล้ว', showConfirmButton:false, timer:1500});">คัดลอก</button>
                </div>
                <p class="text-xs text-gray-500 mt-1">ชื่อบัญชี: บจก. ลูมิน่า บิวตี้</p>
            </div>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" class="max-w-sm mx-auto">
            <input type="hidden" name="action" value="upload_slip">
            <label class="upload-area block w-full rounded-2xl cursor-pointer p-6 mb-6 group">
                <input type="file" name="slip_image" class="hidden" accept="image/*" id="slipInput" onchange="previewFileName(this)">
                <div class="flex flex-col items-center gap-2">
                    <div class="w-12 h-12 bg-pink-100 dark:bg-gray-700 text-primary rounded-full flex items-center justify-center group-hover:scale-110 transition-transform">
                        <span class="material-icons-round text-2xl">cloud_upload</span>
                    </div>
                    <span class="font-bold text-gray-700 dark:text-gray-200" id="fileNameText">คลิกเพื่ออัปโหลดสลิปโอนเงิน</span>
                    <span class="text-xs text-gray-400">รองรับไฟล์ JPG, PNG</span>
                </div>
            </label>

            <button type="submit" class="w-full bg-primary hover:bg-pink-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/30 flex items-center justify-center gap-2 transition-transform transform hover:-translate-y-1">
                ยืนยันการชำระเงิน <span class="material-icons-round">arrow_forward</span>
            </button>
        </form>
    </div>
    
    <?php elseif ($payment_status === 'success'): ?>
    <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] shadow-soft border border-pink-50 dark:border-gray-700 p-10 md:p-16 text-center max-w-2xl mx-auto animate-[fadeIn_0.5s_ease-out]">
        <div class="w-24 h-24 bg-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm border-[6px] border-green-50">
            <span class="material-icons-round text-5xl">check</span>
        </div>
        <h1 class="text-3xl md:text-4xl font-extrabold text-gray-800 dark:text-white mb-3 tracking-tight">ชำระเงินสำเร็จ!</h1>
        <p class="text-gray-500 text-lg mb-8">ขอบคุณสำหรับการสั่งซื้อ หมายเลขออเดอร์ของคุณคือ <span class="font-bold text-primary">#<?= $orderNo ?></span></p>

        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-2xl p-6 mb-10 max-w-sm mx-auto border border-gray-100 dark:border-gray-600">
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">ระบบจะพากลับไปหน้าหลักอัตโนมัติใน</p>
            <div class="text-4xl font-display font-black text-primary animate-pulse" id="countdown">10</div>
            <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">วินาที</p>
        </div>

        <a href="../home.php" class="inline-flex items-center justify-center gap-2 px-8 py-3.5 bg-gray-900 hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100 text-white rounded-full font-bold transition-transform transform hover:-translate-y-1 shadow-md">
            กลับสู่หน้าหลักทันที
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
        <div class="w-24 h-24 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm border-[6px] border-red-50">
            <span class="material-icons-round text-5xl">close</span>
        </div>
        <h1 class="text-3xl md:text-4xl font-extrabold text-gray-800 dark:text-white mb-3 tracking-tight">การชำระเงินล้มเหลว</h1>
        <p class="text-red-500 text-lg mb-8 font-medium"><?= $error_msg ?></p>

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

    // เปลี่ยนข้อความเมื่อเลือกไฟล์สลิป
    function previewFileName(input) {
        const textSpan = document.getElementById('fileNameText');
        const uploadArea = input.closest('.upload-area');
        if (input.files && input.files[0]) {
            textSpan.textContent = "แนบไฟล์แล้ว: " + input.files[0].name;
            textSpan.classList.add('text-primary');
            uploadArea.classList.add('active');
        } else {
            textSpan.textContent = "คลิกเพื่ออัปโหลดสลิปโอนเงิน";
            textSpan.classList.remove('text-primary');
            uploadArea.classList.remove('active');
        }
    }
</script>
</body></html>