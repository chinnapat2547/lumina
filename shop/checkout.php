<?php
session_start();
require_once '../config/connectdbuser.php';

// ==========================================
// 1. ตรวจสอบการล็อกอิน และดึงข้อมูลผู้ใช้เบื้องต้น
// ==========================================
$isLoggedIn = false;
$isAdmin = false;
$profileImage = "https://ui-avatars.com/api/?name=Guest&background=E5E7EB&color=9CA3AF"; 
$userData = ['u_username' => 'ผู้เยี่ยมชม', 'u_email' => 'กรุณาเข้าสู่ระบบ'];
$totalCartItems = 0;

// 🟢 แก้ไข: ตรวจสอบ Session ของ Admin ก่อน 🟢
if (isset($_SESSION['admin_id'])) {
    $isLoggedIn = true;
    $isAdmin = true;
    $userData['u_username'] = $_SESSION['admin_username'] ?? 'Admin';
    $userData['u_email'] = 'Administrator Mode';
    $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($userData['u_username']) . "&background=a855f7&color=fff";
    
} elseif (isset($_SESSION['u_id'])) {
    $isLoggedIn = true;
    $u_id = $_SESSION['u_id'];
    
    // ดึงข้อมูลผู้ใช้
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
    
    // นับจำนวนสินค้าในตะกร้า
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
// 2. ดึงข้อมูลที่อยู่จากตาราง user_address
// ==========================================
$addresses = [];
$userFullName = $userData['u_name'] ?? $userData['u_username'];
$userPhone = "ยังไม่ได้ระบุเบอร์โทรศัพท์";
$userAddress = "ยังไม่ได้ระบุที่อยู่จัดส่ง กรุณาเพิ่มที่อยู่ในหน้าโปรไฟล์";
$hasAddress = false; // 🟢 ตัวแปรสำหรับเช็คว่ามีที่อยู่หรือไม่ 🟢

$sqlAddr = "SELECT * FROM `user_address` WHERE u_id = ? ORDER BY is_default DESC, created_at DESC";
if ($stmtAddr = mysqli_prepare($conn, $sqlAddr)) {
    mysqli_stmt_bind_param($stmtAddr, "i", $u_id);
    mysqli_stmt_execute($stmtAddr);
    $resAddr = mysqli_stmt_get_result($stmtAddr);
    while($row = mysqli_fetch_assoc($resAddr)) {
        $addresses[] = $row;
    }
    mysqli_stmt_close($stmtAddr);
}

// ถ้ามีที่อยู่ในระบบ ให้เอาที่อยู่แรก (หรือ default) มาเป็นค่าเริ่มต้น
if (count($addresses) > 0) {
    $hasAddress = true;
    $userFullName = $addresses[0]['recipient_name'];
    $userPhone = $addresses[0]['phone'];
    $userAddress = $addresses[0]['address_line'] . ' ' . $addresses[0]['district'] . ' ' . $addresses[0]['province'] . ' ' . $addresses[0]['zipcode'];
} else {
    // ถ้าไม่มีใน user_address ให้ดึงจากตาราง user แทน
    if (!empty($userData['u_phone'])) {
        $userPhone = $userData['u_phone'];
    }
    if (!empty($userData['u_address'])) {
        $userAddress = $userData['u_address'];
        $hasAddress = true;
    }
}

// ==========================================
// 3. ดึงข้อมูลบัตรเครดิตจากตาราง payment
// ==========================================
$savedCards = [];
$hasSavedCard = false;
$sqlCard = "SELECT * FROM `payment` WHERE u_id = ? ORDER BY is_default DESC, created_at DESC";
if ($stmtCard = mysqli_prepare($conn, $sqlCard)) {
    mysqli_stmt_bind_param($stmtCard, "i", $u_id);
    mysqli_stmt_execute($stmtCard);
    $resCard = mysqli_stmt_get_result($stmtCard);
    while($row = mysqli_fetch_assoc($resCard)) {
        $savedCards[] = $row;
    }
    mysqli_stmt_close($stmtCard);
}
if(count($savedCards) > 0) {
    $hasSavedCard = true;
    $savedCardLast4 = $savedCards[0]['card_last4'];
    $savedCardType = $savedCards[0]['card_type'];
} else {
    $savedCardLast4 = 'XXXX';
    $savedCardType = 'VISA';
}

// ==========================================
// 4. ดึงข้อมูลสินค้าในตะกร้า & คำนวณราคา
// ==========================================
$cartItems = [];
$subtotal = 0;
$totalCartItems = 0;

$sqlCart = "SELECT c.cart_id, c.quantity, c.selected_color, p.p_id, p.p_name, p.p_price, p.p_image 
            FROM `cart` c 
            JOIN `product` p ON c.p_id = p.p_id 
            WHERE c.u_id = ?";
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

// ถ้าไม่มีสินค้าในตะกร้า ให้กลับไปหน้าตะกร้า
if (count($cartItems) === 0) {
    header("Location: cart.php");
    exit();
}

$isFreeShippingEligible = ($subtotal >= 1000);
$standardCost = $isFreeShippingEligible ? 0 : 50;
$expressCost = $isFreeShippingEligible ? 50 : 100;

$shippingCost = $standardCost; 
$discount = 0; 

// 🟢 แก้ที่ 5: เพิ่มตัวแปรสำหรับรับส่วนลด (จำลองไว้ก่อน)
if (isset($_SESSION['applied_discount_amount'])) {
    $discount = $_SESSION['applied_discount_amount']; 
}

$netTotal = $subtotal + $shippingCost - $discount;
if ($netTotal < 0) $netTotal = 0; 
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>สรุปคำสั่งซื้อ - Lumina Beauty</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#F43F85",
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
                    "soft": "0 4px 20px -2px rgba(244, 63, 133, 0.1)",
                    "glow": "0 0 15px rgba(244, 63, 133, 0.3)"
                }
            },
        },
    }
</script>
<style>
    body { font-family: 'Prompt', sans-serif; }
    .glass-panel { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(244, 63, 133, 0.1); }
    .dark .glass-panel { background: rgba(31, 27, 36, 0.85); border-bottom: 1px solid rgba(255,255,255,0.05); }
    .cloud-gradient {
        background: radial-gradient(circle at 10% 20%, rgba(244, 63, 133, 0.05) 0%, transparent 30%),
                    radial-gradient(circle at 90% 80%, rgba(14, 165, 233, 0.05) 0%, transparent 30%);
    }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #fce7f3; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
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
                    
                    <?php if($isLoggedIn): ?>
                    <div class="absolute right-0 hidden pt-4 top-full w-[320px] z-50 group-hover:block cursor-default">
                        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-[0_10px_40px_-10px_rgba(236,45,136,0.2)] border border-pink-100 dark:border-gray-700 overflow-hidden p-5 relative">
                            <div class="text-center mb-4">
                                <span class="text-sm font-medium <?= $isAdmin ? 'text-purple-500 font-bold' : 'text-gray-500 dark:text-gray-400' ?>">
                                    <?= $isAdmin ? 'Administrator Mode' : htmlspecialchars($userData['u_email'] ?? '') ?>
                                </span>
                            </div>
                            <div class="flex justify-center relative mb-4">
                                <div class="rounded-full p-[3px] <?= $isAdmin ? 'bg-purple-500' : 'bg-primary' ?> shadow-md">
                                    <div class="bg-white dark:bg-gray-800 rounded-full p-[3px] w-16 h-16">
                                        <img src="<?= htmlspecialchars($profileImage) ?>" alt="Profile" class="w-full h-full rounded-full object-cover">
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mt-2 mb-6">
                                <h3 class="text-[22px] font-bold text-gray-800 dark:text-white">สวัสดี คุณ <?= htmlspecialchars($userData['u_username'] ?? 'User') ?></h3>
                            </div>
                            <div class="flex flex-col gap-3 mt-2">
                                <?php if($isAdmin): ?>
                                    <a href="../admin/dashboard.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-purple-500 hover:bg-purple-500 hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-purple-500">
                                        <span class="material-icons-round text-[20px]">admin_panel_settings</span> สำหรับ Admin
                                    </a>
                                <?php else: ?>
                                    <a href="../profile/account.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-primary hover:bg-primary hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-primary">
                                        จัดการบัญชี
                                    </a>
                                <?php endif; ?>
                                <a href="../auth/logout.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-red-500 hover:bg-red-500 hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-red-500">
                                    <span class="material-icons-round text-[20px]">logout</span> ออกจากระบบ
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto w-full px-4 pb-16 lg:px-16">
    <div class="mb-10 mt-4">
        <div class="flex items-center justify-between max-w-3xl mx-auto relative">
            <div class="absolute top-1/2 left-0 w-full h-1 bg-gray-200 dark:bg-gray-700 -z-10 -translate-y-1/2 rounded-full"></div>
            <div class="absolute top-1/2 left-0 w-[50%] h-1 bg-primary -z-10 -translate-y-1/2 rounded-full transition-all duration-500"></div>
            
            <a href="cart.php" class="flex flex-col items-center gap-2 group cursor-pointer">
                <div class="size-10 rounded-full bg-primary text-white flex items-center justify-center border-2 border-white dark:border-gray-800 shadow-sm group-hover:scale-110 transition-transform">
                    <span class="material-icons-round text-lg">shopping_cart</span>
                </div>
                <span class="text-xs font-bold text-primary">ตะกร้าสินค้า</span>
            </a>
            <div class="flex flex-col items-center gap-2">
                <div class="size-12 rounded-full bg-primary text-white flex items-center justify-center border-4 border-pink-100 dark:border-gray-800 shadow-glow">
                    <span class="material-icons-round text-xl">receipt_long</span>
                </div>
                <span class="text-xs font-bold text-primary">ที่อยู่ & ชำระเงิน</span>
            </div>
            <div class="flex flex-col items-center gap-2">
                <div class="size-10 rounded-full bg-white dark:bg-gray-800 text-gray-300 dark:text-gray-600 flex items-center justify-center border-2 border-gray-100 dark:border-gray-700 shadow-sm">
                    <span class="material-icons-round text-lg">check_circle</span>
                </div>
                <span class="text-xs font-medium text-gray-400">เสร็จสิ้น</span>
            </div>
        </div>
    </div>

    <form action="process_checkout.php" method="POST" id="checkoutForm" onsubmit="return validateCheckout()" class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        <input type="hidden" id="hasAddressFlag" value="<?= $hasAddress ? 'true' : 'false' ?>">
        
        <div class="lg:col-span-2 space-y-6">
            
            <section class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-soft border border-pink-50 dark:border-gray-700 p-6 sm:p-8 overflow-hidden relative">
                <div class="absolute top-0 right-0 p-4 opacity-[0.03] pointer-events-none">
                    <span class="material-icons-round text-[100px] text-primary">local_shipping</span>
                </div>
                <div class="flex items-center justify-between mb-4 relative z-10">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="material-icons-round text-primary bg-pink-50 dark:bg-gray-700 p-1.5 rounded-xl">location_on</span>
                        ที่อยู่จัดส่ง
                    </h2>
                    <button type="button" onclick="openAddressModal()" class="text-primary text-sm font-bold flex items-center gap-1 hover:bg-pink-50 dark:hover:bg-gray-700 px-4 py-2 rounded-full transition-all border border-transparent hover:border-pink-100">
                        <span class="material-icons-round text-sm">edit</span> เปลี่ยนที่อยู่
                    </button>
                </div>
                
                <div class="bg-blue-50/50 dark:bg-gray-700/50 rounded-2xl p-5 border border-blue-100 dark:border-gray-600 relative z-10 group transition-colors">
                    <p class="font-bold text-gray-800 dark:text-white text-lg flex items-center gap-2">
                        <span id="displayFullName"><?= htmlspecialchars($userFullName) ?></span>
                        <span class="text-gray-500 text-sm font-normal flex items-center gap-1 bg-white dark:bg-gray-800 px-2 py-0.5 rounded-md border border-gray-100 dark:border-gray-600">
                            <span class="material-icons-round text-[14px]">phone</span> 
                            <span id="displayPhone"><?= htmlspecialchars($userPhone) ?></span>
                        </span>
                    </p>
                    <p id="displayAddress" class="text-gray-600 dark:text-gray-300 text-sm mt-3 leading-relaxed <?= !$hasAddress ? 'text-red-500 font-medium' : '' ?>">
                        <?= htmlspecialchars($userAddress) ?>
                    </p>
                </div>
                <input type="hidden" name="shipping_address" id="inputShippingAddress" value="<?= htmlspecialchars($userAddress) ?>">
            </section>

            <section class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-soft border border-pink-50 dark:border-gray-700 p-6 sm:p-8">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-5 flex items-center gap-2">
                    <span class="material-icons-round text-blue-500 bg-blue-50 dark:bg-gray-700 p-1.5 rounded-xl">package_2</span>
                    ตัวเลือกการจัดส่ง
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="relative flex cursor-pointer rounded-2xl border-2 border-primary bg-pink-50/30 dark:bg-gray-700 p-5 focus:outline-none transition-all duration-300">
                        <input checked class="sr-only" name="shipping_method" type="radio" value="standard" onchange="updateShipping(this.value)"/>
                        <div class="flex flex-1 items-start gap-4">
                            <div class="flex h-5 items-center mt-1">
                                <div class="size-5 rounded-full border-[5px] border-primary bg-white shadow-sm transition-all duration-300"></div>
                            </div>
                            <div class="flex flex-col">
                                <span class="block text-base font-bold text-gray-900 dark:text-white">ส่งธรรมดา (Standard)</span>
                                <span class="mt-1 flex items-center text-xs text-gray-500 dark:text-gray-400">ได้รับภายใน 2-3 วันทำการ</span>
                                <span class="mt-2 text-sm font-bold text-primary" id="priceLabelStandard">
                                    <?= $standardCost == 0 ? 'ฟรี' : '฿' . number_format($standardCost) ?>
                                </span>
                            </div>
                        </div>
                        <span class="material-icons-round text-primary text-3xl opacity-80 transition-colors duration-300">local_shipping</span>
                    </label>
                    <label class="relative flex cursor-pointer rounded-2xl border-2 border-gray-100 dark:border-gray-600 bg-white dark:bg-gray-800 p-5 hover:border-pink-200 focus:outline-none transition-all duration-300">
                        <input class="sr-only" name="shipping_method" type="radio" value="express" onchange="updateShipping(this.value)"/>
                        <div class="flex flex-1 items-start gap-4">
                            <div class="flex h-5 items-center mt-1">
                                <div class="size-5 rounded-full border-2 border-gray-300 dark:border-gray-500 bg-white transition-all duration-300"></div>
                            </div>
                            <div class="flex flex-col">
                                <span class="block text-base font-bold text-gray-700 dark:text-gray-300">ส่งด่วน (Express)</span>
                                <span class="mt-1 flex items-center text-xs text-gray-500 dark:text-gray-400">ได้รับภายในวันถัดไป</span>
                                <span class="mt-2 text-sm font-bold text-gray-700 dark:text-gray-300" id="priceLabelExpress">
                                    ฿<?= number_format($expressCost) ?>
                                </span>
                            </div>
                        </div>
                        <span class="material-icons-round text-gray-300 text-3xl transition-colors duration-300">bolt</span>
                    </label>
                </div>
            </section>

            <section class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-soft border border-pink-50 dark:border-gray-700 p-6 sm:p-8 overflow-hidden">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-5 flex items-center gap-2">
                    <span class="material-icons-round text-purple-500 bg-purple-50 dark:bg-gray-700 p-1.5 rounded-xl">account_balance_wallet</span>
                    วิธีการชำระเงิน
                </h2>
                
                <div class="space-y-3" id="paymentContainer">
                    <label class="payment-option flex items-center gap-4 p-4 rounded-2xl border-2 border-primary bg-pink-50/30 dark:bg-gray-700 cursor-pointer transition-all duration-300">
                        <input checked class="w-5 h-5 text-primary focus:ring-primary accent-primary" name="payment_method" type="radio" value="promptpay" onchange="updatePaymentUI()"/>
                        <span class="material-icons-round text-primary text-2xl transition-colors">qr_code_2</span>
                        <span class="flex-1 font-bold text-gray-900 dark:text-white transition-colors">พร้อมเพย์ QR Code หรือโอนผ่านบัญชีธนาคาร</span>
                        <span class="text-[10px] bg-primary/10 text-primary px-3 py-1 rounded-full font-bold shadow-sm border border-primary/20">ยอดนิยม</span>
                    </label>

                    <label class="payment-option flex items-center gap-4 p-4 rounded-2xl border-2 border-gray-100 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-all duration-300">
                        <input class="w-5 h-5 text-primary focus:ring-primary accent-primary" name="payment_method" type="radio" value="credit_card" data-has-card="<?= $hasSavedCard ? 'true' : 'false' ?>" onchange="updatePaymentUI()"/>
                        <span class="material-icons-round text-gray-400 text-2xl transition-colors">credit_card</span>
                        
                        <div class="flex-1 flex flex-col justify-center">
                            <span class="font-medium text-gray-700 dark:text-gray-300 transition-colors">บัตรเครดิต / เดบิต</span>
                            <div id="savedCardInfo" class="<?= $hasSavedCard ? 'block' : 'hidden' ?> mt-0.5">
                                <span class="text-[11px] text-green-500 font-bold">ลงท้ายด้วย **** <span id="displayCardLast4"><?= htmlspecialchars($savedCardLast4) ?></span> (<span id="displayCardType"><?= htmlspecialchars($savedCardType) ?></span>)</span>
                            </div>
                        </div>

                        <div class="flex gap-1.5">
                            <div class="h-6 w-10 bg-gray-100 dark:bg-gray-600 rounded flex items-center justify-center text-[8px] font-bold text-blue-800">VISA</div>
                            <div class="h-6 w-10 bg-gray-100 dark:bg-gray-600 rounded flex items-center justify-center text-[8px] font-bold text-red-500">MASTER</div>
                        </div>
                    </label>

                    <label class="payment-option flex items-center gap-4 p-4 rounded-2xl border-2 border-gray-100 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-all duration-300">
                        <input class="w-5 h-5 text-primary focus:ring-primary accent-primary" name="payment_method" type="radio" value="cod" onchange="updatePaymentUI()"/>
                        <span class="material-icons-round text-gray-400 text-2xl transition-colors">delivery_dining</span>
                        <span class="flex-1 font-medium text-gray-700 dark:text-gray-300 transition-colors">ชำระเงินปลายทาง (COD)</span>
                    </label>
                </div>

                <div class="mt-8 bg-gradient-to-r from-blue-50 to-pink-50 dark:from-gray-700 dark:to-gray-600 rounded-2xl p-4 flex items-center gap-4 relative overflow-hidden border border-white dark:border-gray-600">
                    <div class="size-12 relative flex items-center justify-center bg-white dark:bg-gray-800 rounded-full shadow-sm">
                        <span class="material-icons-round text-2xl text-green-500 font-bold">shield</span>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800 dark:text-white text-sm">การชำระเงินปลอดภัย 100%</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">ข้อมูลของคุณได้รับการเข้ารหัสและปกป้องด้วยมาตรฐานความปลอดภัยสูงสุด</p>
                    </div>
                </div>
            </section>
        </div>

        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-glow border border-primary/10 p-6 sm:p-8 sticky top-32">
                <h2 class="text-xl font-extrabold text-gray-900 dark:text-white mb-6 border-b border-gray-100 dark:border-gray-700 pb-4 flex items-center gap-2">
                    <span class="material-icons-round text-primary">receipt_long</span> สรุปรายการสั่งซื้อ
                </h2>
                
                <div class="space-y-5 mb-6 max-h-[360px] overflow-y-auto pr-2 custom-scrollbar">
                    <?php foreach($cartItems as $item): 
                        $imgUrl = (!empty($item['p_image']) && file_exists("../uploads/products/".$item['p_image'])) 
                                  ? "../uploads/products/".$item['p_image'] 
                                  : "https://via.placeholder.com/150";
                    ?>
                    <div class="flex gap-4 group">
                        <div class="size-16 rounded-xl bg-gray-50 dark:bg-gray-700 overflow-hidden shrink-0 border border-gray-100 dark:border-gray-600 relative">
                            <img alt="<?= htmlspecialchars($item['p_name']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-300" src="<?= $imgUrl ?>"/>
                            <span class="absolute -top-2 -right-2 bg-gray-800 text-white text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full"><?= $item['quantity'] ?></span>
                        </div>
                        <div class="flex-1 min-w-0 flex flex-col justify-center">
                            <p class="text-sm font-bold text-gray-800 dark:text-white truncate" title="<?= htmlspecialchars($item['p_name']) ?>"><?= htmlspecialchars($item['p_name']) ?></p>
                            <?php if(!empty($item['selected_color'])): ?>
                                <p class="text-[10px] text-primary mt-0.5">สี: <?= htmlspecialchars($item['selected_color']) ?></p>
                            <?php endif; ?>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium"><?= $item['quantity'] ?> x ฿<?= number_format($item['p_price']) ?></span>
                                <span class="text-sm font-extrabold text-primary">฿<?= number_format($item['p_price'] * $item['quantity']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mb-6 relative">
                    <input class="w-full text-sm bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-full focus:border-primary focus:ring-1 focus:ring-primary pl-5 pr-24 py-3.5 outline-none transition-all dark:text-white" placeholder="ใส่โค้ดส่วนลด (ถ้ามี)" type="text" id="discountCodeInput"/>
                    <button type="button" onclick="applyDiscountCode()" class="absolute right-1.5 top-1.5 bg-primary/10 text-primary font-bold text-sm px-4 py-2 rounded-full hover:bg-primary hover:text-white transition-all">ใช้โค้ด</button>
                </div>

                <div class="space-y-3 pt-5 border-t border-gray-100 dark:border-gray-700">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400 font-medium">รวมค่าสินค้า</span>
                        <span class="text-gray-800 dark:text-white font-bold">฿<span id="subtotalDisplay"><?= number_format($subtotal, 2) ?></span></span>
                    </div>
                    
                    <div id="discountRow" class="flex justify-between text-sm <?= $discount > 0 ? '' : 'hidden' ?>">
                        <span class="text-gray-500 dark:text-gray-400 font-medium">ส่วนลด (Discount)</span>
                        <span class="text-primary font-bold">-฿<span id="discountDisplay"><?= number_format($discount, 2) ?></span></span>
                    </div>
                    
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400 font-medium">ค่าจัดส่ง</span>
                        <span class="text-gray-800 dark:text-white font-bold" id="shippingDisplay">
                            <?= $shippingCost > 0 ? '฿' . number_format($shippingCost, 2) : '<span class="text-green-500">ฟรี</span>' ?>
                        </span>
                    </div>
                    
                    <div class="flex justify-between items-end pt-5 border-t border-gray-100 dark:border-gray-700 mt-4">
                        <span class="text-gray-900 dark:text-white font-bold text-lg">ยอดสุทธิ</span>
                        <span class="text-3xl font-extrabold text-primary tracking-tight">฿<span id="netTotalDisplay"><?= number_format($netTotal, 2) ?></span></span>
                    </div>
                </div>

                <button type="submit" class="w-full mt-8 bg-primary hover:bg-pink-600 text-white font-bold py-4 rounded-2xl shadow-[0_8px_25px_-8px_rgba(244,63,133,0.6)] flex items-center justify-center gap-2 transition-all transform hover:-translate-y-1 relative overflow-hidden group text-lg">
                    ยืนยันคำสั่งซื้อ
                    <span class="material-icons-round ml-2 group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </button>
                <p class="text-[11px] text-center text-gray-400 mt-4 px-4 leading-relaxed">การกดปุ่มยืนยัน ถือว่าคุณยอมรับ<a href="#" class="text-primary hover:underline font-bold">ข้อกำหนดและเงื่อนไข</a>ของทางร้าน</p>
            </div>
        </div>
    </form>
</main>

<div id="selectCardModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden items-center justify-center opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] w-full max-w-lg overflow-hidden shadow-2xl transform scale-95 transition-transform duration-300 modal-content border border-pink-50 dark:border-gray-700">
        
        <div class="px-8 py-5 flex justify-between items-center border-b border-gray-50 dark:border-gray-700">
            <h2 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <span class="material-icons-round text-primary">credit_card</span> เลือกบัตรที่ต้องการใช้
            </h2>
            <button type="button" onclick="closeSelectCardModal(false)" class="text-gray-400 hover:text-gray-700 transition-colors">
                <span class="material-icons-round text-xl">close</span>
            </button>
        </div>
        
        <div class="p-8 pt-4 pb-10 bg-white dark:bg-gray-800 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
            
            <?php if($hasSavedCard): ?>
                <?php foreach($savedCards as $index => $card): ?>
                <label class="relative flex cursor-pointer rounded-2xl border-2 <?= $index == 0 ? 'border-primary bg-pink-50/30' : 'border-gray-100 bg-white' ?> dark:border-gray-700 p-5 focus:outline-none transition-all hover:border-pink-200" onclick="selectCardUI('<?= htmlspecialchars($card['card_last4']) ?>', '<?= htmlspecialchars($card['card_type']) ?>', this)">
                    <input <?= $index == 0 ? 'checked' : '' ?> class="sr-only" name="select_card_radio" type="radio" value="<?= $card['card_id'] ?>"/>
                    <div class="flex flex-1 items-center gap-4">
                        <div class="flex h-5 items-center">
                            <div class="radio-indicator size-5 rounded-full border-[5px] <?= $index == 0 ? 'border-primary' : 'border-gray-300 border-2' ?> bg-white shadow-sm"></div>
                        </div>
                        <div class="w-12 h-8 bg-gray-100 rounded flex items-center justify-center text-[10px] font-bold <?= strtolower($card['card_type']) == 'visa' ? 'text-blue-800' : 'text-red-500' ?>">
                            <?= htmlspecialchars($card['card_type']) ?>
                        </div>
                        <div class="flex flex-col flex-1">
                            <span class="block text-base font-bold text-gray-900 dark:text-white">ลงท้ายด้วย **** <?= htmlspecialchars($card['card_last4']) ?></span>
                        </div>
                    </div>
                </label>
                <?php endforeach; ?>
                
                <a href="/../profile/payment.php" class="block w-full border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-2xl p-4 text-center hover:border-primary hover:bg-pink-50/50 dark:hover:bg-gray-700 transition-colors group mt-2">
                    <span class="font-bold text-sm text-gray-600 dark:text-gray-300 group-hover:text-primary flex items-center justify-center gap-2">
                        <span class="material-icons-round text-lg">add_circle_outline</span> เพิ่มบัตรเครดิต/เดบิตใบใหม่
                    </span>
                </a>

                <div class="mt-6 flex justify-between gap-4">
                    <button type="button" onclick="closeSelectCardModal(false)" class="flex-1 py-3.5 bg-gray-100 text-gray-700 font-bold rounded-2xl hover:bg-gray-200 transition">ยกเลิก</button>
                    <button type="button" onclick="confirmCardSelection()" class="flex-1 py-3.5 bg-primary text-white font-bold rounded-2xl hover:bg-pink-600 transition shadow-lg shadow-primary/30">ยืนยัน</button>
                </div>

            <?php else: ?>
                <div class="text-center mt-2 mb-6">
                    <p class="text-gray-500 font-medium mb-4">คุณยังไม่มีบัตรเครดิตที่บันทึกไว้</p>
                    <a href="payment.php" class="w-full border-[2px] border-dashed border-primary text-primary rounded-2xl py-6 flex flex-col items-center justify-center hover:bg-pink-50 transition-colors cursor-pointer">
                        <div class="w-10 h-10 rounded-full border border-pink-200 flex items-center justify-center mb-2 bg-white">
                            <span class="material-icons-round text-xl">add</span>
                        </div>
                        <span class="font-bold text-sm">เพิ่มบัตรเครดิต/เดบิตใหม่</span>
                    </a>
                </div>
                
                <div class="flex justify-between gap-4">
                    <button type="button" onclick="closeSelectCardModal(false)" class="flex-1 py-3 bg-gray-100 text-gray-700 font-bold rounded-2xl hover:bg-gray-200 transition">ยกเลิก</button>
                    <button type="button" disabled class="flex-1 py-3 bg-primary text-white font-bold rounded-2xl opacity-50 cursor-not-allowed">ยืนยัน</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="addressModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden items-center justify-center opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-gray-800 rounded-[2rem] w-full max-w-2xl overflow-hidden shadow-2xl transform scale-95 transition-transform duration-300 modal-content border border-pink-50 dark:border-gray-700">
        <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800/50">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <span class="material-icons-round text-primary">location_on</span> เลือกที่อยู่จัดส่ง
            </h2>
            <button type="button" onclick="closeAddressModal()" class="w-8 h-8 rounded-full bg-white dark:bg-gray-700 text-gray-400 hover:text-red-500 border border-gray-200 dark:border-gray-600 flex items-center justify-center transition-colors shadow-sm">
                <span class="material-icons-round text-[18px]">close</span>
            </button>
        </div>
        
        <div class="p-6 max-h-[60vh] overflow-y-auto custom-scrollbar bg-white dark:bg-gray-800 space-y-4">
            <?php if(count($addresses) > 0): ?>
                <?php foreach($addresses as $index => $addr): 
                    $fullAddr = $addr['address_line'] . ' ' . $addr['district'] . ' ' . $addr['province'] . ' ' . $addr['zipcode'];
                ?>
                <label class="relative flex cursor-pointer rounded-2xl border-2 <?= $index == 0 ? 'border-primary bg-pink-50/30' : 'border-gray-100 bg-white' ?> dark:border-gray-700 p-5 focus:outline-none transition-all hover:border-pink-200" onclick="selectAddress('<?= htmlspecialchars($addr['recipient_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($addr['phone'], ENT_QUOTES) ?>', '<?= htmlspecialchars($fullAddr, ENT_QUOTES) ?>', this)">
                    <input <?= $index == 0 ? 'checked' : '' ?> class="sr-only" name="select_address_radio" type="radio" value="<?= $addr['addr_id'] ?>"/>
                    <div class="flex flex-1 items-start gap-4">
                        <div class="flex h-5 items-center mt-1">
                            <div class="radio-indicator size-5 rounded-full border-[5px] <?= $index == 0 ? 'border-primary' : 'border-gray-300 border-2' ?> bg-white shadow-sm"></div>
                        </div>
                        <div class="flex flex-col w-full">
                            <div class="flex justify-between items-start mb-1">
                                <span class="block text-base font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($addr['recipient_name']) ?> <span class="text-sm text-gray-500 font-normal ml-2"><?= htmlspecialchars($addr['phone']) ?></span></span>
                            </div>
                            <span class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed"><?= htmlspecialchars($fullAddr) ?></span>
                        </div>
                    </div>
                </label>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-gray-500 my-4">คุณยังไม่มีที่อยู่ที่บันทึกไว้ในระบบ</p>
            <?php endif; ?>

            <a href="../profile/address.php" class="block w-full border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-2xl p-6 text-center hover:border-primary hover:bg-pink-50/50 dark:hover:bg-gray-700 transition-colors group mt-4">
                <div class="w-12 h-12 bg-gray-100 dark:bg-gray-700 text-gray-400 group-hover:text-primary group-hover:bg-white rounded-full flex items-center justify-center mx-auto mb-3 transition-colors shadow-sm">
                    <span class="material-icons-round text-2xl">add_location_alt</span>
                </div>
                <span class="font-bold text-gray-600 dark:text-gray-300 group-hover:text-primary">เพิ่มที่อยู่จัดส่งใหม่</span>
            </a>
        </div>
        
        <div class="p-5 border-t border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 flex justify-end gap-3 rounded-b-[2rem]">
            <button type="button" onclick="closeAddressModal()" class="px-6 py-2.5 rounded-full font-bold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">ปิดหน้าต่าง</button>
            <button type="button" onclick="confirmAddressSelection()" class="px-8 py-2.5 rounded-full font-bold text-white bg-primary hover:bg-pink-600 shadow-md transition-colors">ยืนยัน</button>
        </div>
    </div>
</div>

<script>
    if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        localStorage.setItem('theme', htmlEl.classList.contains('dark') ? 'dark' : 'light');
    }

    const subtotal = <?= $subtotal ?>;
    let currentDiscount = <?= $discount ?>; // 🟢 ส่วนลดปัจจุบัน 🟢
    const isFreeShippingEligible = (subtotal >= 1000); 

    function updateShipping(method) {
        let shippingCost = 0;
        if (method === 'standard') {
            shippingCost = isFreeShippingEligible ? 0 : 50;
        } else if (method === 'express') {
            shippingCost = isFreeShippingEligible ? 50 : 100;
        }
        
        let netTotal = subtotal + shippingCost - currentDiscount;
        if(netTotal < 0) netTotal = 0;

        const shipDisplay = document.getElementById('shippingDisplay');
        if (shippingCost === 0) {
            shipDisplay.innerHTML = '<span class="text-green-500">ฟรี</span>';
        } else {
            shipDisplay.innerText = '฿' + shippingCost.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
        document.getElementById('netTotalDisplay').innerText = netTotal.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        
        const allLabels = document.querySelectorAll('input[name="shipping_method"]');
        allLabels.forEach(input => {
            const label = input.closest('label');
            const radioCircle = label.querySelector('.size-5');
            const icon = label.querySelector('.material-icons-round');
            
            if(input.checked) {
                label.classList.add('border-primary', 'bg-pink-50/30');
                label.classList.remove('border-gray-100', 'dark:border-gray-600', 'bg-white', 'dark:bg-gray-800');
                radioCircle.classList.add('border-[5px]', 'border-primary', 'bg-white');
                radioCircle.classList.remove('border-2', 'border-gray-300', 'dark:border-gray-500');
                icon.classList.add('text-primary'); icon.classList.remove('text-gray-300');
            } else {
                label.classList.remove('border-primary', 'bg-pink-50/30');
                label.classList.add('border-gray-100', 'dark:border-gray-600', 'bg-white', 'dark:bg-gray-800');
                radioCircle.classList.remove('border-[5px]', 'border-primary', 'bg-white');
                radioCircle.classList.add('border-2', 'border-gray-300', 'dark:border-gray-500');
                icon.classList.remove('text-primary'); icon.classList.add('text-gray-300');
            }
        });
    }

    function updatePaymentUI() {
        const paymentOptions = document.querySelectorAll('input[name="payment_method"]');
        
        paymentOptions.forEach(input => {
            const label = input.closest('label');
            const icon = label.querySelector('.material-icons-round');
            const textSpan = label.querySelector('.font-medium, .font-bold');

            if (input.checked) {
                label.classList.add('border-primary', 'bg-pink-50/30');
                label.classList.remove('border-gray-100', 'hover:bg-gray-50');
                icon.classList.add('text-primary'); icon.classList.remove('text-gray-400');
                textSpan.classList.add('font-bold', 'text-gray-900'); textSpan.classList.remove('font-medium', 'text-gray-700');

                if (input.value === 'credit_card') {
                    const hasCard = input.getAttribute('data-has-card') === 'true';
                    if (!hasCard) {
                        // 🟢 ถ้าไม่มีบัตรให้โชว์หน้าต่างรูปที่ 1 🟢
                        openSelectCardModal(); 
                    } else {
                        openSelectCardModal();
                    }
                }
            } else {
                label.classList.remove('border-primary', 'bg-pink-50/30');
                label.classList.add('border-gray-100', 'hover:bg-gray-50');
                icon.classList.remove('text-primary'); icon.classList.add('text-gray-400');
                textSpan.classList.remove('font-bold', 'text-gray-900'); textSpan.classList.add('font-medium', 'text-gray-700');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => { updatePaymentUI(); });

    // 🟢 ตรวจสอบที่อยู่ก่อน Submit (แก้ที่ 3) 🟢
    function validateCheckout() {
        const hasAddress = document.getElementById('hasAddressFlag').value === 'true';
        if (!hasAddress) {
            Swal.fire({
                title: 'ไม่พบที่อยู่จัดส่ง',
                text: 'กรุณาเพิ่มที่อยู่จัดส่งก่อนทำการยืนยันคำสั่งซื้อ',
                icon: 'warning',
                confirmButtonColor: '#F43F85',
                confirmButtonText: 'รับทราบ',
                customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-full px-8' }
            });
            return false; // ห้ามฟอร์มส่งข้อมูล
        }
        return true;
    }

    // 🟢 ฟังก์ชันจำลองใช้โค้ดส่วนลด (แก้ที่ 5) 🟢
    function applyDiscountCode() {
        const codeInput = document.getElementById('discountCodeInput').value.trim().toUpperCase();
        if(codeInput === '') return;

        // จำลอง: ถ้าพิมพ์โค้ด LUMINA50 จะได้ลด 50 บาท
        if(codeInput === 'LUMINA50') {
            currentDiscount = 50;
            document.getElementById('discountDisplay').innerText = currentDiscount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            document.getElementById('discountRow').classList.remove('hidden');
            
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'ใช้โค้ดส่วนลดสำเร็จ', showConfirmButton: false, timer: 2000 });
        } else {
            Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'โค้ดส่วนลดไม่ถูกต้องหรือหมดอายุ', showConfirmButton: false, timer: 2000 });
        }
        
        // อัปเดตยอดรวมใหม่หลังจากคิดส่วนลด
        const selectedShipping = document.querySelector('input[name="shipping_method"]:checked').value;
        updateShipping(selectedShipping);
    }

    // ที่อยู่
    let tempSelectedName = '', tempSelectedPhone = '', tempSelectedAddress = '';
    const addressModal = document.getElementById('addressModal');

    function openAddressModal() {
        addressModal.classList.remove('hidden'); addressModal.classList.add('flex');
        setTimeout(() => { addressModal.classList.remove('opacity-0'); addressModal.querySelector('.modal-content').classList.remove('scale-95'); }, 10);
    }
    function closeAddressModal() {
        addressModal.classList.add('opacity-0'); addressModal.querySelector('.modal-content').classList.add('scale-95');
        setTimeout(() => { addressModal.classList.add('hidden'); addressModal.classList.remove('flex'); }, 300);
    }
    function selectAddress(name, phone, address, labelElement) {
        tempSelectedName = name; tempSelectedPhone = phone; tempSelectedAddress = address;
        const allLabels = addressModal.querySelectorAll('label');
        allLabels.forEach(lbl => {
            const ind = lbl.querySelector('.radio-indicator');
            lbl.classList.remove('border-primary', 'bg-pink-50/30'); lbl.classList.add('border-gray-100', 'bg-white');
            ind.classList.remove('border-primary', 'border-[5px]'); ind.classList.add('border-gray-300', 'border-2');
        });
        labelElement.classList.add('border-primary', 'bg-pink-50/30'); labelElement.classList.remove('border-gray-100', 'bg-white');
        const indicator = labelElement.querySelector('.radio-indicator');
        indicator.classList.add('border-primary', 'border-[5px]'); indicator.classList.remove('border-gray-300', 'border-2');
    }
    function confirmAddressSelection() {
        if (tempSelectedName !== '') {
            document.getElementById('displayFullName').innerText = tempSelectedName;
            document.getElementById('displayPhone').innerText = tempSelectedPhone;
            document.getElementById('displayAddress').innerText = tempSelectedAddress;
            document.getElementById('displayAddress').classList.remove('text-red-500', 'font-medium');
            document.getElementById('inputShippingAddress').value = tempSelectedAddress;
            document.getElementById('hasAddressFlag').value = 'true';
        }
        closeAddressModal();
    }

    // บัตรเครดิต
    const selectCardModal = document.getElementById('selectCardModal');
    const cardFormModal = document.getElementById('cardFormModal');
    let tempCardLast4 = '', tempCardType = '';

    function openSelectCardModal() {
        selectCardModal.classList.remove('hidden'); selectCardModal.classList.add('flex');
        setTimeout(() => { selectCardModal.classList.remove('opacity-0'); selectCardModal.querySelector('.modal-content').classList.remove('scale-95'); }, 10);
    }
    function closeSelectCardModal(isConfirmed) {
        selectCardModal.classList.add('opacity-0'); selectCardModal.querySelector('.modal-content').classList.add('scale-95');
        setTimeout(() => { selectCardModal.classList.add('hidden'); selectCardModal.classList.remove('flex'); }, 300);
        if (!isConfirmed) { document.querySelector('input[value="promptpay"]').checked = true; updatePaymentUI(); }
    }
    function selectCardUI(last4, type, labelElement) {
        tempCardLast4 = last4; tempCardType = type;
        const allLabels = selectCardModal.querySelectorAll('label');
        allLabels.forEach(lbl => {
            const ind = lbl.querySelector('.radio-indicator');
            lbl.classList.remove('border-primary', 'bg-pink-50/30'); lbl.classList.add('border-gray-100', 'bg-white');
            ind.classList.remove('border-primary', 'border-[5px]'); ind.classList.add('border-gray-300', 'border-2');
        });
        labelElement.classList.add('border-primary', 'bg-pink-50/30'); labelElement.classList.remove('border-gray-100', 'bg-white');
        const indicator = labelElement.querySelector('.radio-indicator');
        indicator.classList.add('border-primary', 'border-[5px]'); indicator.classList.remove('border-gray-300', 'border-2');
    }
    function confirmCardSelection() {
        const cardInput = document.querySelector('input[value="credit_card"]');
        cardInput.setAttribute('data-has-card', 'true');
        document.getElementById('savedCardInfo').classList.remove('hidden');
        document.getElementById('displayCardLast4').innerText = tempCardLast4 || 'XXXX';
        document.getElementById('displayCardType').innerText = tempCardType || 'VISA';
        closeSelectCardModal(true);
    }
    function openCardFormModal() {
        cardFormModal.classList.remove('hidden'); cardFormModal.classList.add('flex');
        setTimeout(() => { cardFormModal.classList.remove('opacity-0'); cardFormModal.querySelector('.modal-content').classList.remove('scale-95'); }, 10);
    }
    function closeCardFormModal() {
        cardFormModal.classList.add('opacity-0'); cardFormModal.querySelector('.modal-content').classList.add('scale-95');
        setTimeout(() => { cardFormModal.classList.add('hidden'); cardFormModal.classList.remove('flex'); }, 300);
        document.querySelector('input[value="promptpay"]').checked = true; updatePaymentUI();
    }
    function saveNewCard() {
        const rawCardNum = document.getElementById('newCardNumber').value;
        const last4 = rawCardNum.length >= 4 ? rawCardNum.slice(-4) : '1234';
        const cardInput = document.querySelector('input[value="credit_card"]');
        cardInput.setAttribute('data-has-card', 'true');
        document.getElementById('savedCardInfo').classList.remove('hidden');
        document.getElementById('displayCardLast4').innerText = last4;
        document.getElementById('displayCardType').innerText = 'VISA';

        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'บันทึกบัตรใหม่สำเร็จ', showConfirmButton: false, timer: 2000 });
        
        cardFormModal.classList.add('opacity-0'); cardFormModal.querySelector('.modal-content').classList.add('scale-95');
        setTimeout(() => { cardFormModal.classList.add('hidden'); cardFormModal.classList.remove('flex'); }, 300);
    }
</script>

</body></html>