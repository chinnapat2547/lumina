<?php
session_start();
require_once '../config/connectdbuser.php';

// ==========================================
// 1. ตรวจสอบล็อกอินและเตรียมข้อมูล Navbar
// ==========================================
$isLoggedIn = false;
$isAdmin = false;
$profileImage = "https://ui-avatars.com/api/?name=Guest&background=E5E7EB&color=9CA3AF"; 
$userData = ['u_username' => 'ผู้เยี่ยมชม', 'u_email' => 'กรุณาเข้าสู่ระบบ'];
$totalCartItems = 0;

if (isset($_SESSION['admin_id'])) {
    $isLoggedIn = true;
    $isAdmin = true;
    $userData['u_username'] = $_SESSION['admin_username'] ?? 'Admin';
    $userData['u_email'] = 'Administrator Mode';
    $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($userData['u_username']) . "&background=a855f7&color=fff";
    
} elseif (isset($_SESSION['u_id'])) {
    $isLoggedIn = true;
    $u_id = $_SESSION['u_id'];
    
    $sqlUser = "SELECT a.u_username, a.u_email, u.u_image FROM `account` a LEFT JOIN `user` u ON a.u_id = u.u_id WHERE a.u_id = ?";
    if ($stmtUser = mysqli_prepare($conn, $sqlUser)) {
        mysqli_stmt_bind_param($stmtUser, "i", $u_id);
        mysqli_stmt_execute($stmtUser);
        $resultUser = mysqli_stmt_get_result($stmtUser);
        
        if ($rowUser = mysqli_fetch_assoc($resultUser)) {
            $userData = $rowUser;
            
            $physical_path = __DIR__ . "/../profile/uploads/" . $userData['u_image'];
            if (!empty($userData['u_image']) && file_exists($physical_path)) {
                $profileImage = "../profile/uploads/" . $userData['u_image'];
            } else {
                $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($userData['u_username']) . "&background=F43F85&color=fff";
            }
        }
        mysqli_stmt_close($stmtUser);
    }
    
    $sqlCartCount = "SELECT SUM(quantity) as total_qty FROM `cart` WHERE u_id = ?";
    if ($stmtCartCount = mysqli_prepare($conn, $sqlCartCount)) {
        mysqli_stmt_bind_param($stmtCartCount, "i", $u_id);
        mysqli_stmt_execute($stmtCartCount);
        $resultCartCount = mysqli_stmt_get_result($stmtCartCount);
        if ($rowCartCount = mysqli_fetch_assoc($resultCartCount)) {
            $totalCartItems = $rowCartCount['total_qty'] ?? 0;
        }
        mysqli_stmt_close($stmtCartCount);
    }
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
if (isset($_SESSION['order_saved']) && $_SESSION['order_saved'] === true) {
    $payment_status = 'success';
    $orderNo = $_SESSION['last_order_no'] ?? 'N/A';
    $totalCartItems = 0; 
} else {
    if (in_array($checkoutData['payment_method'], ['credit_card', 'cod'])) {
        $should_save_order = true;
    } 
    elseif ($checkoutData['payment_method'] === 'promptpay') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'upload_slip') {
            if (isset($_FILES['slip_image']) && $_FILES['slip_image']['error'] == 0) {
                
                // 🟢 แก้ที่ 2: ตรวจสอบนามสกุล, ชนิดไฟล์ และขนาดไฟล์สลิป 🟢
                $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
                $allowed_mime = ['image/jpeg', 'image/png', 'image/webp'];
                $max_size = 2 * 1024 * 1024; // 2MB
                
                $file_name = $_FILES['slip_image']['name'];
                $file_size = $_FILES['slip_image']['size'];
                $file_tmp = $_FILES['slip_image']['tmp_name'];
                $file_type = mime_content_type($file_tmp);
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if ($file_size > $max_size) {
                    $payment_status = 'failed';
                    $error_msg = "ขนาดไฟล์สลิปใหญ่เกินไป (ต้องไม่เกิน 2MB)";
                } elseif (!in_array($ext, $allowed_ext) || !in_array($file_type, $allowed_mime)) {
                    $payment_status = 'failed';
                    $error_msg = "รองรับเฉพาะไฟล์รูปภาพ (JPG, JPEG, PNG, WEBP) เท่านั้น";
                } else {
                    $uploadDir = '../uploads/slips/';
                    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
                    
                    $slip_name = "slip_" . time() . "_" . $u_id . "." . $ext;
                    
                    if(move_uploaded_file($file_tmp, $uploadDir . $slip_name)){
                        $should_save_order = true; 
                    } else {
                        $payment_status = 'failed';
                        $error_msg = "ระบบขัดข้อง: ไม่สามารถบันทึกไฟล์รูปภาพสลิปได้";
                    }
                }
            } else {
                $payment_status = 'failed';
                $error_msg = "กรุณาแนบไฟล์สลิปหลักฐานการโอนเงิน เพื่อยืนยันการสั่งซื้อ";
            }
        } else {
            $payment_status = 'pending';
        }
    }

    // ==========================================
    // 5. รันคำสั่งบันทึกลง Database
    // ==========================================
    if ($should_save_order) {
        $orderNo = "ORD" . date('Ymd') . rand(1000, 9999);
        
        // 🟢 แก้ที่ 3: ให้สถานะเป็น processing ทั้งหมด (COD ก็จะเป็น 'ที่ต้องจัดส่ง' ไม่ค้าง 'รอชำระ') 🟢
        $status = 'processing';
        
        $pm = $checkoutData['payment_method'];
        $sm = $checkoutData['shipping_method'];
        
        $sqlInsertOrder = "INSERT INTO `orders` (order_no, u_id, total_amount, status, payment_method, shipping_method, slip_image) VALUES (?, ?, ?, ?, ?, ?, ?)";
        if ($stmtOrder = mysqli_prepare($conn, $sqlInsertOrder)) {
            mysqli_stmt_bind_param($stmtOrder, "sidsiss", $orderNo, $u_id, $netTotal, $status, $pm, $sm, $slip_name);
            
            if(mysqli_stmt_execute($stmtOrder)) {
                $newOrderId = mysqli_insert_id($conn);
                
                foreach ($cartItems as $item) {
                    $img = $item['p_image'] ?? '';
                    $sqlInsertItem = "INSERT INTO `order_items` (order_id, p_id, p_name, p_image, price, quantity) VALUES (?, ?, ?, ?, ?, ?)";
                    if ($stmtItem = mysqli_prepare($conn, $sqlInsertItem)) {
                        mysqli_stmt_bind_param($stmtItem, "iisdsi", $newOrderId, $item['p_id'], $item['p_name'], $img, $item['p_price'], $item['quantity']);
                        mysqli_stmt_execute($stmtItem);
                        mysqli_stmt_close($stmtItem);
                    }
                    
                    $sqlUpdateStock = "UPDATE `product` SET p_stock = p_stock - ? WHERE p_id = ?";
                    if ($stmtStock = mysqli_prepare($conn, $sqlUpdateStock)) {
                        mysqli_stmt_bind_param($stmtStock, "ii", $item['quantity'], $item['p_id']);
                        mysqli_stmt_execute($stmtStock);
                        mysqli_stmt_close($stmtStock);
                    }
                }
                
                $sqlClearCart = "DELETE FROM `cart` WHERE u_id = ?";
                if ($stmtClear = mysqli_prepare($conn, $sqlClearCart)) {
                    mysqli_stmt_bind_param($stmtClear, "i", $u_id);
                    mysqli_stmt_execute($stmtClear);
                    mysqli_stmt_close($stmtClear);
                }
                
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
                    <span class="text-[16px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">สินค้า</span>
                    <span class="text-[12px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Shop)</span>
                </a>
                <div class="relative group">
                    <button class="flex flex-col items-center justify-center transition pb-1 pt-1">
                        <div class="flex items-center gap-1">
                            <span class="text-[16px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">หมวดหมู่</span>
                            <span class="material-icons-round text-sm text-gray-700 dark:text-gray-200 group-hover:text-primary">expand_more</span>
                        </div>
                        <span class="text-[12px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Categories)</span>
                    </button>
                </div>
                <a class="group flex flex-col items-center justify-center transition" href="promotions.php">
                    <span class="text-[16px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">โปรโมชั่น</span>
                    <span class="text-[12px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Sale)</span>
                </a>
                <a class="group flex flex-col items-center justify-center transition" href="../contact.php">
                    <span class="text-[16px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">ติดต่อเรา</span>
                    <span class="text-[12px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Contact)</span>
                </a>
            </div>

            <div class="flex items-center space-x-2 sm:space-x-4">
                <a href="favorites.php" id="nav-fav-icon" class="text-gray-500 dark:text-gray-300 hover:text-pink-600 transition relative flex items-center justify-center group">
                    <span class="material-icons-round text-2xl transition-transform duration-300 group-hover:scale-110">favorite_border</span>
                </a>
                <a href="cart.php" id="nav-cart-icon" class="relative w-10 h-10 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-800 rounded-full transition-all cursor-pointer">
                    <span class="material-icons-round text-2xl transition-transform duration-300">shopping_bag</span>
                    <span id="cart-badge" class="absolute -top-1.5 -right-2 bg-primary text-white text-[10px] font-bold rounded-full h-[18px] w-[18px] flex items-center justify-center border-2 border-white dark:border-gray-800 transition-transform duration-300"><?= $totalCartItems ?></span>
                </a>
                <button class="w-10 h-10 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-800 rounded-full transition-all" onclick="toggleTheme()">
                    <span class="material-icons-round dark:hidden text-2xl">dark_mode</span>
                    <span class="material-icons-round hidden dark:block text-yellow-400 text-2xl">light_mode</span>
                </button>
                
                <div class="relative group flex items-center">
                    <a href="<?= $isAdmin ? '../admin/dashboard.php' : '../profile/account.php' ?>" class="block w-10 h-10 rounded-full bg-gradient-to-tr <?= $isAdmin ? 'from-purple-400 to-indigo-400' : 'from-pink-300 to-purple-300' ?> p-0.5 shadow-sm hover:shadow-md hover:scale-105 transition-all cursor-pointer">
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
                    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-full p-1 shadow-sm border border-gray-100">
                        <img src="qr.JPEG" class="h-4">
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 shadow-sm text-left h-full flex flex-col justify-center border border-gray-100 dark:border-gray-600">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center">
                            <span class="material-icons-round text-white text-sm">account_balance</span>
                        </div>
                        <span class="font-bold text-gray-800 dark:text-white">ธนาคารกรุงเทพ</span>
                    </div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-lg font-mono text-gray-700 dark:text-gray-300 font-bold tracking-widest">414-425-3830</span>
                        <button type="button" class="text-primary hover:text-pink-600 transition-colors" onclick="navigator.clipboard.writeText('4144253830'); alert('คัดลอกเลขบัญชีแล้ว');"><span class="material-icons-round text-[20px]">content_copy</span></button>
                    </div>
                    <p class="text-xs text-gray-500">ชื่อบัญชี: บจก. ลูมิน่า บิวตี้</p>
                </div>
            </div>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" class="max-w-sm mx-auto">
            <input type="hidden" name="action" value="upload_slip">
            <label class="upload-area block w-full rounded-2xl cursor-pointer p-6 mb-6 group bg-white dark:bg-gray-800 relative overflow-hidden shadow-sm">
                <input type="file" name="slip_image" class="absolute inset-0 opacity-0 cursor-pointer" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" id="slipInput" onchange="previewFileName(this)">
                <div class="flex flex-col items-center gap-3">
                    <div class="w-14 h-14 bg-pink-50 dark:bg-gray-700 text-primary rounded-full flex items-center justify-center group-hover:scale-110 transition-transform shadow-sm">
                        <span class="material-icons-round text-3xl">cloud_upload</span>
                    </div>
                    <span class="font-bold text-gray-700 dark:text-gray-200 text-sm" id="fileNameText">คลิกเพื่ออัปโหลดสลิปโอนเงิน</span>
                    <span class="text-xs text-gray-400">รองรับไฟล์ JPG, JPEG, PNG, WEBP (ไม่เกิน 2MB)</span>
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

    // ฟังก์ชันพรีวิวและตรวจสอบไฟล์สลิป
    function previewFileName(input) {
        const textSpan = document.getElementById('fileNameText');
        const uploadArea = input.closest('.upload-area');
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const fileSize = file.size;
            const fileType = file.type;
            const validTypes = ['image/jpeg', 'image/png', 'image/webp'];

            // 1. ตรวจสอบนามสกุล/ประเภทไฟล์
            if (!validTypes.includes(fileType)) {
                Swal.fire({
                    icon: 'error',
                    title: 'รูปแบบไฟล์ไม่รองรับ',
                    text: 'กรุณาอัปโหลดเฉพาะไฟล์รูปภาพ (JPG, JPEG, PNG, WEBP) เท่านั้น',
                    confirmButtonColor: '#ec2d88',
                    customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-full px-6' }
                });
                resetUploadUI(input, textSpan, uploadArea);
                return;
            }

            // 2. ตรวจสอบขนาดไฟล์ (ไม่เกิน 2MB = 2097152 Bytes)
            if (fileSize > 2097152) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ขนาดไฟล์ใหญ่เกินไป',
                    text: 'กรุณาเลือกไฟล์สลิปที่มีขนาดไม่เกิน 2MB',
                    confirmButtonColor: '#ec2d88',
                    customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-full px-6' }
                });
                resetUploadUI(input, textSpan, uploadArea);
                return;
            }
            
            // ถ้าผ่านทุกเงื่อนไข ให้เปลี่ยนสีกรอบเป็นสีชมพู
            textSpan.textContent = "อัปโหลดแล้ว: " + file.name;
            textSpan.classList.add('text-primary');
            uploadArea.classList.add('active', 'bg-pink-50/50', 'border-primary');
            uploadArea.classList.remove('bg-white');
            
        } else {
            resetUploadUI(input, textSpan, uploadArea);
        }
    }

    // ฟังก์ชันช่วยรีเซ็ตหน้าตาปุ่มอัปโหลดกลับเป็นแบบเดิม
    function resetUploadUI(input, textSpan, uploadArea) {
        input.value = "";
        textSpan.textContent = "คลิกเพื่ออัปโหลดสลิปโอนเงิน";
        textSpan.classList.remove('text-primary');
        uploadArea.classList.remove('active', 'bg-pink-50/50', 'border-primary');
        uploadArea.classList.add('bg-white');
    }
</script>
</body></html>