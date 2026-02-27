<?php
session_start();
require_once '../config/connectdbuser.php';

// ==========================================
// 1. ตรวจสอบการล็อกอิน และดึงข้อมูลผู้ใช้ (สำหรับ Navbar และ ที่อยู่)
// ==========================================
if (!isset($_SESSION['u_id'])) {
    // ถ้ายังไม่ล็อกอิน ให้เด้งไปหน้า login
    header("Location: ../auth/login.php");
    exit();
}

$u_id = $_SESSION['u_id'];
$isLoggedIn = true;
$isAdmin = isset($_SESSION['admin_id']) ? true : false;
$userData = ['u_username' => 'ผู้ใช้งาน', 'u_email' => ''];
$profileImage = "https://ui-avatars.com/api/?name=User&background=ec2d88&color=fff";

// ตัวแปรสำหรับเก็บข้อมูลที่อยู่จัดส่ง
$userFullName = "ผู้ใช้งาน";
$userPhone = "";
$userAddress = "";

$sqlUser = "SELECT a.u_username, a.u_email, a.u_name, u.u_image, u.u_phone, u.u_address 
            FROM `account` a 
            LEFT JOIN `user` u ON a.u_id = u.u_id 
            WHERE a.u_id = ?";
if ($stmtUser = mysqli_prepare($conn, $sqlUser)) {
    mysqli_stmt_bind_param($stmtUser, "i", $u_id);
    mysqli_stmt_execute($stmtUser);
    $resultUser = mysqli_stmt_get_result($stmtUser);
    if ($rowUser = mysqli_fetch_assoc($resultUser)) {
        $userData = $rowUser;
        $userFullName = !empty($rowUser['u_name']) ? $rowUser['u_name'] : $rowUser['u_username'];
        $userPhone = !empty($rowUser['u_phone']) ? $rowUser['u_phone'] : "ยังไม่ได้ระบุเบอร์โทรศัพท์";
        $userAddress = !empty($rowUser['u_address']) ? $rowUser['u_address'] : "ยังไม่ได้ระบุที่อยู่จัดส่ง กรุณาเพิ่มที่อยู่ในหน้าโปรไฟล์";
        
        if (!empty($rowUser['u_image']) && file_exists("../uploads/" . $rowUser['u_image'])) {
            $profileImage = "../uploads/" . $rowUser['u_image'];
        } else {
            $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($rowUser['u_username']) . "&background=ec2d88&color=fff";
        }
    }
    mysqli_stmt_close($stmtUser);
}

// ==========================================
// 2. ดึงข้อมูลสินค้าในตะกร้า
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

// คำนวณค่าจัดส่ง (ตัวอย่าง: ถ้ายอดรวม >= 500 ส่งฟรี, ถ้าน้อยกว่าคิด 50 บาท)
$shippingCost = ($subtotal >= 500) ? 0 : 50;
$discount = 0; // พื้นที่สำหรับระบบคูปองในอนาคต
$netTotal = $subtotal + $shippingCost - $discount;

?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>สรุปคำสั่งซื้อ - Lumina Beauty</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>

<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#ec2d88", // อิงตามสีหลักเว็บ
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
            <div class="flex items-center space-x-2 sm:space-x-4">
                
                <div class="hidden md:flex items-center relative mr-2">
                    <form action="products.php" method="GET">
                        <input type="text" name="search" placeholder="ค้นหาสินค้า..." class="pl-10 pr-4 py-2 bg-pink-50 dark:bg-gray-800 border-none rounded-full text-sm focus:ring-2 focus:ring-primary w-48 lg:w-64 transition-all placeholder-gray-400 dark:text-white outline-none">
                        <button type="submit" class="material-icons-round absolute left-3 top-2 text-gray-400 text-lg">search</button>
                    </form>
                </div>

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
<main class="max-w-7xl mx-auto w-full px-4 pb-16 lg:px-16">
    <div class="mb-10 mt-4">
        <div class="flex items-center justify-between max-w-3xl mx-auto relative">
            <div class="absolute top-1/2 left-0 w-full h-1 bg-gray-200 dark:bg-gray-700 -z-10 -translate-y-1/2 rounded-full"></div>
            <div class="absolute top-1/2 left-0 w-[50%] h-1 bg-primary -z-10 -translate-y-1/2 rounded-full"></div>
            
            <a href="cart.php" class="flex flex-col items-center gap-2 group cursor-pointer">
                <div class="size-10 rounded-full bg-primary text-white flex items-center justify-center border-2 border-white dark:border-gray-800 shadow-sm group-hover:scale-110 transition-transform">
                    <span class="material-icons-round text-lg">shopping_cart</span>
                </div>
                <span class="text-xs font-bold text-primary">ตะกร้าสินค้า</span>
            </a>
            <div class="flex flex-col items-center gap-2">
                <div class="size-12 rounded-full bg-primary text-white flex items-center justify-center border-4 border-accent-pink shadow-glow">
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

    <form action="process_checkout.php" method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
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
                    <a href="../profile/account.php" class="text-primary text-sm font-bold flex items-center gap-1 hover:bg-pink-50 dark:hover:bg-gray-700 px-4 py-2 rounded-full transition-all">
                        <span class="material-icons-round text-sm">edit</span> เปลี่ยนที่อยู่
                    </a>
                </div>
                <div class="bg-blue-50/50 dark:bg-gray-700/50 rounded-2xl p-5 border border-blue-100 dark:border-gray-600 relative z-10">
                    <p class="font-bold text-gray-800 dark:text-white text-lg">
                        <?= htmlspecialchars($userFullName) ?> 
                        <span class="text-gray-500 text-sm font-normal ml-2"><i class="material-icons-round text-[14px] align-middle">phone</i> <?= htmlspecialchars($userPhone) ?></span>
                    </p>
                    <p class="text-gray-600 dark:text-gray-300 text-sm mt-2 leading-relaxed">
                        <?= htmlspecialchars($userAddress) ?>
                    </p>
                </div>
                <input type="hidden" name="shipping_address" value="<?= htmlspecialchars($userAddress) ?>">
            </section>

            <section class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-soft border border-pink-50 dark:border-gray-700 p-6 sm:p-8">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-5 flex items-center gap-2">
                    <span class="material-icons-round text-blue-500 bg-blue-50 dark:bg-gray-700 p-1.5 rounded-xl">package_2</span>
                    ตัวเลือกการจัดส่ง
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="relative flex cursor-pointer rounded-2xl border-2 border-primary bg-pink-50/30 dark:bg-gray-700 p-5 focus:outline-none transition-all">
                        <input checked class="sr-only" name="shipping_method" type="radio" value="standard" onchange="updateShipping(this.value)"/>
                        <div class="flex flex-1 items-start gap-4">
                            <div class="flex h-5 items-center mt-1">
                                <div class="size-5 rounded-full border-[5px] border-primary bg-white shadow-sm"></div>
                            </div>
                            <div class="flex flex-col">
                                <span class="block text-base font-bold text-gray-900 dark:text-white">ส่งธรรมดา (Standard)</span>
                                <span class="mt-1 flex items-center text-xs text-gray-500 dark:text-gray-400">ได้รับภายใน 2-3 วันทำการ</span>
                                <span class="mt-2 text-sm font-bold text-primary">฿50.00</span>
                            </div>
                        </div>
                        <span class="material-icons-round text-primary text-3xl opacity-80">local_shipping</span>
                    </label>
                    <label class="relative flex cursor-pointer rounded-2xl border-2 border-gray-100 dark:border-gray-600 bg-white dark:bg-gray-800 p-5 hover:border-pink-200 focus:outline-none transition-all">
                        <input class="sr-only" name="shipping_method" type="radio" value="express" onchange="updateShipping(this.value)"/>
                        <div class="flex flex-1 items-start gap-4">
                            <div class="flex h-5 items-center mt-1">
                                <div class="size-5 rounded-full border-2 border-gray-300 dark:border-gray-500 bg-white"></div>
                            </div>
                            <div class="flex flex-col">
                                <span class="block text-base font-bold text-gray-700 dark:text-gray-300">ส่งด่วน (Express)</span>
                                <span class="mt-1 flex items-center text-xs text-gray-500 dark:text-gray-400">ได้รับภายในวันถัดไป</span>
                                <span class="mt-2 text-sm font-bold text-gray-700 dark:text-gray-300">฿100.00</span>
                            </div>
                        </div>
                        <span class="material-icons-round text-gray-300 text-3xl">bolt</span>
                    </label>
                </div>
            </section>

            <section class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-soft border border-pink-50 dark:border-gray-700 p-6 sm:p-8 overflow-hidden">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-5 flex items-center gap-2">
                    <span class="material-icons-round text-purple-500 bg-purple-50 dark:bg-gray-700 p-1.5 rounded-xl">account_balance_wallet</span>
                    วิธีการชำระเงิน
                </h2>
                <div class="space-y-3">
                    <label class="flex items-center gap-4 p-4 rounded-2xl border-2 border-primary bg-pink-50/30 dark:bg-gray-700 cursor-pointer transition-all">
                        <input checked class="w-5 h-5 text-primary focus:ring-primary accent-primary" name="payment_method" type="radio" value="promptpay"/>
                        <span class="material-icons-round text-primary text-2xl">qr_code_2</span>
                        <span class="flex-1 font-bold text-gray-900 dark:text-white">พร้อมเพย์ QR Code (PromptPay)</span>
                        <span class="text-[10px] bg-primary/10 text-primary px-3 py-1 rounded-full font-bold shadow-sm border border-primary/20">ยอดนิยม</span>
                    </label>
                    <label class="flex items-center gap-4 p-4 rounded-2xl border-2 border-gray-100 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-all group">
                        <input class="w-5 h-5 text-primary focus:ring-primary accent-primary" name="payment_method" type="radio" value="credit_card"/>
                        <span class="material-icons-round text-gray-400 group-hover:text-primary transition-colors text-2xl">credit_card</span>
                        <span class="flex-1 font-medium text-gray-700 dark:text-gray-300">บัตรเครดิต / เดบิต</span>
                        <div class="flex gap-1.5">
                            <div class="h-6 w-10 bg-gray-100 dark:bg-gray-600 rounded flex items-center justify-center text-[8px] font-bold text-blue-800">VISA</div>
                            <div class="h-6 w-10 bg-gray-100 dark:bg-gray-600 rounded flex items-center justify-center text-[8px] font-bold text-red-500">MASTER</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-4 p-4 rounded-2xl border-2 border-gray-100 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-all group">
                        <input class="w-5 h-5 text-primary focus:ring-primary accent-primary" name="payment_method" type="radio" value="cod"/>
                        <span class="material-icons-round text-gray-400 group-hover:text-primary transition-colors text-2xl">delivery_dining</span>
                        <span class="flex-1 font-medium text-gray-700 dark:text-gray-300">ชำระเงินปลายทาง (COD)</span>
                    </label>
                </div>

                <div class="mt-8 bg-gradient-to-r from-blue-50 to-pink-50 dark:from-gray-700 dark:to-gray-600 rounded-2xl p-4 flex items-center gap-4 relative overflow-hidden border border-white">
                    <div class="size-12 relative flex items-center justify-center bg-white rounded-full shadow-sm">
                        <span class="material-icons-round text-2xl text-green-500 font-bold">shield</span>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800 dark:text-white text-sm">การชำระเงินปลอดภัย 100%</h4>
                        <p class="text-xs text-gray-500 mt-0.5">ข้อมูลของคุณได้รับการเข้ารหัสและปกป้องด้วยมาตรฐานความปลอดภัยสูงสุด</p>
                    </div>
                </div>
            </section>
        </div>

        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-glow border border-primary/10 p-6 sm:p-8 sticky top-32">
                <h2 class="text-xl font-extrabold text-gray-900 dark:text-white mb-6 border-b border-gray-100 dark:border-gray-700 pb-4 flex items-center gap-2">
                    <span class="material-icons-round text-primary">receipt_long</span> สรุปรายการสั่งซื้อ
                </h2>
                
                <div class="space-y-5 mb-6 max-h-[40vh] overflow-y-auto pr-2 custom-scrollbar">
                    <?php foreach($cartItems as $item): 
                        $imgUrl = (!empty($item['p_image']) && file_exists("../uploads/products/".$item['p_image'])) 
                                  ? "../uploads/products/".$item['p_image'] 
                                  : "https://via.placeholder.com/150";
                    ?>
                    <div class="flex gap-4 group">
                        <div class="size-16 rounded-xl bg-gray-50 dark:bg-gray-700 overflow-hidden shrink-0 border border-gray-100 relative">
                            <img alt="<?= htmlspecialchars($item['p_name']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-300" src="<?= $imgUrl ?>"/>
                            <span class="absolute -top-2 -right-2 bg-gray-800 text-white text-[10px] w-5 h-5 flex items-center justify-center rounded-full"><?= $item['quantity'] ?></span>
                        </div>
                        <div class="flex-1 min-w-0 flex flex-col justify-center">
                            <p class="text-sm font-bold text-gray-800 dark:text-white truncate" title="<?= htmlspecialchars($item['p_name']) ?>"><?= htmlspecialchars($item['p_name']) ?></p>
                            <?php if(!empty($item['selected_color'])): ?>
                                <p class="text-[11px] text-gray-500 mt-0.5">สี: <?= htmlspecialchars($item['selected_color']) ?></p>
                            <?php endif; ?>
                            <div class="flex justify-between items-center mt-1">
                                <span class="text-xs text-gray-500"><?= $item['quantity'] ?> x ฿<?= number_format($item['p_price']) ?></span>
                                <span class="text-sm font-extrabold text-primary">฿<?= number_format($item['p_price'] * $item['quantity']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mb-6 flex gap-2">
                    <input class="flex-1 text-sm bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl focus:border-primary focus:ring-1 focus:ring-primary px-4 py-3 outline-none transition-all dark:text-white" placeholder="ใส่โค้ดส่วนลด (ถ้ามี)" type="text"/>
                    <button type="button" class="bg-primary/10 text-primary font-bold text-sm px-5 py-3 rounded-xl hover:bg-primary hover:text-white transition-all">ใช้โค้ด</button>
                </div>

                <div class="space-y-3 pt-5 border-t border-gray-100 dark:border-gray-700">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 font-medium">รวมค่าสินค้า</span>
                        <span class="text-gray-800 dark:text-white font-bold">฿<span id="subtotalDisplay"><?= number_format($subtotal, 2) ?></span></span>
                    </div>
                    <?php if($discount > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 font-medium">ส่วนลด</span>
                        <span class="text-green-500 font-bold">-฿<?= number_format($discount, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 font-medium">ค่าจัดส่ง</span>
                        <span class="text-gray-800 dark:text-white font-bold" id="shippingDisplay">
                            <?= $shippingCost > 0 ? '฿' . number_format($shippingCost, 2) : '<span class="text-green-500">ฟรี</span>' ?>
                        </span>
                    </div>
                    
                    <div class="flex justify-between items-end pt-5 border-t border-gray-100 dark:border-gray-700 mt-4">
                        <span class="text-gray-900 dark:text-white font-bold">ยอดสุทธิ</span>
                        <span class="text-2xl font-extrabold text-primary tracking-tight">฿<span id="netTotalDisplay"><?= number_format($netTotal, 2) ?></span></span>
                    </div>
                </div>

                <button type="submit" class="w-full mt-8 bg-primary hover:bg-pink-600 text-white font-bold py-4 rounded-2xl shadow-[0_8px_25px_-8px_rgba(236,45,136,0.6)] flex items-center justify-center gap-2 transition-all transform hover:-translate-y-1 relative overflow-hidden group text-lg">
                    <span class="material-icons-round group-hover:translate-x-1 transition-transform">lock</span>
                    ยืนยันคำสั่งซื้อ
                </button>
                <p class="text-[11px] text-center text-gray-400 mt-4 px-4 leading-relaxed">การกดปุ่มยืนยัน ถือว่าคุณยอมรับ<a href="#" class="text-primary hover:underline">ข้อกำหนดและเงื่อนไข</a>ของทางร้าน</p>
            </div>
        </div>
    </form>
</main>

<style>
    /* ปรับแต่ง Scrollbar ในตะกร้า */
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #fce7f3; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
</style>

<script>
    // สลับ Dark Mode (อิงตาม Navbar)
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }
    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        localStorage.setItem('theme', htmlEl.classList.contains('dark') ? 'dark' : 'light');
    }

    // ตัวแปรสำหรับคำนวณราคาแบบ Real-time ด้วย JS
    const subtotal = <?= $subtotal ?>;
    let shippingCost = <?= $shippingCost ?>; 
    const discount = <?= $discount ?>;
    const isFreeShippingEligible = (subtotal >= 500); // เงื่อนไขส่งฟรี

    function updateShipping(method) {
        if (isFreeShippingEligible) {
            shippingCost = 0; // ถ้าได้โปรส่งฟรี ก็ฟรีเสมอ
        } else {
            shippingCost = (method === 'express') ? 100 : 50;
        }
        
        let netTotal = subtotal + shippingCost - discount;

        // อัปเดต UI
        const shipDisplay = document.getElementById('shippingDisplay');
        if (shippingCost === 0) {
            shipDisplay.innerHTML = '<span class="text-green-500">ฟรี</span>';
        } else {
            shipDisplay.innerText = '฿' + shippingCost.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
        
        document.getElementById('netTotalDisplay').innerText = netTotal.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        
        // สลับสีสไตล์กรอบ Radio (ให้สวยงามขึ้น)
        const allLabels = document.querySelectorAll('input[name="shipping_method"]');
        allLabels.forEach(input => {
            const label = input.closest('label');
            const radioCircle = label.querySelector('.size-5');
            const icon = label.querySelector('.material-icons-round');
            
            if(input.checked) {
                label.classList.add('border-primary', 'bg-pink-50/30');
                label.classList.remove('border-gray-100', 'bg-white');
                radioCircle.classList.add('border-[5px]', 'border-primary', 'bg-white');
                radioCircle.classList.remove('border-2', 'border-gray-300');
                icon.classList.add('text-primary');
                icon.classList.remove('text-gray-300');
            } else {
                label.classList.remove('border-primary', 'bg-pink-50/30');
                label.classList.add('border-gray-100', 'bg-white');
                radioCircle.classList.remove('border-[5px]', 'border-primary', 'bg-white');
                radioCircle.classList.add('border-2', 'border-gray-300');
                icon.classList.remove('text-primary');
                icon.classList.add('text-gray-300');
            }
        });
    }
</script>

</body></html>