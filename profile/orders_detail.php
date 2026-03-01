<?php
session_start();
require_once '../config/connectdbuser.php';

// ==========================================
// 1. ตรวจสอบสถานะการล็อกอิน และข้อมูล Navbar
// ==========================================
if (!isset($_SESSION['u_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$isLoggedIn = true;
$isAdmin = isset($_SESSION['admin_id']) ? true : false;
$u_id = $_SESSION['u_id'];
$profileImage = "https://ui-avatars.com/api/?name=User&background=F43F85&color=fff";
$userData = ['u_username' => 'User'];

$sql = "SELECT a.u_username, a.u_email, u.u_image FROM `account` a LEFT JOIN `user` u ON a.u_id = u.u_id WHERE a.u_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $u_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $userData = $row;
        if (!empty($row['u_image']) && file_exists("../profile/uploads/" . $row['u_image'])) {
            $profileImage = "../profile/uploads/" . $row['u_image'];
        } else {
            $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($row['u_username']) . "&background=F43F85&color=fff";
        }
    }
    mysqli_stmt_close($stmt);
}

$totalCartItems = 0; 
$sqlCartCount = "SELECT SUM(quantity) as total_qty FROM `cart` WHERE u_id = ?";
if ($stmtCartCount = mysqli_prepare($conn, $sqlCartCount)) {
    mysqli_stmt_bind_param($stmtCartCount, "i", $u_id);
    mysqli_stmt_execute($stmtCartCount);
    $resultCartCount = mysqli_stmt_get_result($stmtCartCount);
    if ($rowCartCount = mysqli_fetch_assoc($resultCartCount)) {
        $totalCartItems = ($rowCartCount['total_qty'] !== null) ? (int)$rowCartCount['total_qty'] : 0;
    }
    mysqli_stmt_close($stmtCartCount);
}

// ==========================================
// 2. จัดการรับค่า POST (ยกเลิก, คืนเงิน)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $target_order_id = (int)$_POST['order_id'];
    
    if ($_POST['action'] === 'cancel_order') {
        $updSql = "UPDATE `orders` SET status = 'cancelled' WHERE order_id = ? AND u_id = ?";
        if ($stmtUpd = mysqli_prepare($conn, $updSql)) {
            mysqli_stmt_bind_param($stmtUpd, "ii", $target_order_id, $u_id);
            mysqli_stmt_execute($stmtUpd);
            mysqli_stmt_close($stmtUpd);
        }
        $_SESSION['toast_msg'] = "ยกเลิกคำสั่งซื้อเรียบร้อยแล้ว";
        header("Location: orders_detail.php?id=" . $target_order_id);
        exit();
    }
    
    if ($_POST['action'] === 'refund_order') {
        $_SESSION['toast_msg'] = "ส่งคำขอคืนเงินสำเร็จ ทีมงานจะติดต่อกลับภายใน 24 ชม.";
        header("Location: orders_detail.php?id=" . $target_order_id);
        exit();
    }
}

// ==========================================
// 3. ดึงข้อมูลคำสั่งซื้อและสินค้า
// ==========================================
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    header("Location: orders.php");
    exit();
}

$order = null;
$sqlOrder = "SELECT * FROM `orders` WHERE order_id = ? AND u_id = ?";
if ($stmtOrder = mysqli_prepare($conn, $sqlOrder)) {
    mysqli_stmt_bind_param($stmtOrder, "ii", $order_id, $u_id);
    mysqli_stmt_execute($stmtOrder);
    $resOrder = mysqli_stmt_get_result($stmtOrder);
    $order = mysqli_fetch_assoc($resOrder);
    mysqli_stmt_close($stmtOrder);
}

if (!$order) {
    header("Location: orders.php");
    exit();
}

// ดึงที่อยู่จัดส่ง (ถ้าในบิลไม่มี ให้ดึงจากข้อมูล Profile ปกติมาสำรอง)
$displayAddress = isset($order['shipping_address']) && !empty(trim($order['shipping_address'])) ? $order['shipping_address'] : '';
if (empty($displayAddress)) {
    $sqlFallback = "SELECT * FROM user_address WHERE u_id = ? ORDER BY is_default DESC LIMIT 1";
    if($stmtFB = mysqli_prepare($conn, $sqlFallback)) {
        mysqli_stmt_bind_param($stmtFB, "i", $u_id);
        mysqli_stmt_execute($stmtFB);
        $resFB = mysqli_stmt_get_result($stmtFB);
        if($fb = mysqli_fetch_assoc($resFB)){
            $displayAddress = $fb['address_line'] . ' ' . $fb['district'] . ' ' . $fb['province'] . ' ' . $fb['zipcode'];
        }
        mysqli_stmt_close($stmtFB);
    }
    if (empty(trim($displayAddress))) {
        $displayAddress = "ไม่มีข้อมูลที่อยู่จัดส่ง";
    }
}

// ดึงรายการสินค้า พร้อมหมวดหมู่ (Category) และดึงรูปจาก Product ปกติมาสำรองด้วย
$items = [];
$subtotal = 0;
$sqlItems = "SELECT oi.*, p.p_image as real_p_image, c.c_name 
             FROM `order_items` oi 
             LEFT JOIN `product` p ON oi.p_id = p.p_id 
             LEFT JOIN `category` c ON p.c_id = c.c_id 
             WHERE oi.order_id = ?";
if ($stmtItems = mysqli_prepare($conn, $sqlItems)) {
    mysqli_stmt_bind_param($stmtItems, "i", $order_id);
    mysqli_stmt_execute($stmtItems);
    $resItems = mysqli_stmt_get_result($stmtItems);
    while($item = mysqli_fetch_assoc($resItems)){
        $items[] = $item;
        $subtotal += ($item['price'] * $item['quantity']);
    }
    mysqli_stmt_close($stmtItems);
}

// ==========================================
// 4. จัดเตรียมข้อมูลสำหรับแสดงผล
// ==========================================
$thai_months = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
$time = strtotime($order['created_at']);
$formatted_date = date('d', $time) . ' ' . $thai_months[date('n', $time)] . ' ' . (date('Y', $time) + 543) . ' • ' . date('H:i', $time) . ' น.';

// 🟢 สร้างเลขพัสดุแบบสุ่ม 1 ครั้ง (ล็อกค่าตาม Order ID ทำให้เลขไม่เปลี่ยนเวลาย้อนกลับมาดู)
mt_srand($order_id * 999); 
$trackingNo = 'TH' . mt_rand(1000000000, 9999999999) . 'TH';
mt_srand(); // รีเซ็ต Seed คืนค่าปกติเพื่อไม่ให้กระทบฟังก์ชันอื่น

function getStatusBadge($status) {
    switch($status) {
        case 'pending': return ['text' => 'ที่ต้องชำระเงิน', 'color' => 'bg-orange-100 text-orange-600 border-orange-200', 'icon' => 'payment'];
        case 'processing': return ['text' => 'กำลังเตรียมจัดส่ง', 'color' => 'bg-blue-100 text-blue-600 border-blue-200', 'icon' => 'inventory_2'];
        case 'shipped': return ['text' => 'อยู่ระหว่างจัดส่ง', 'color' => 'bg-purple-100 text-purple-600 border-purple-200', 'icon' => 'local_shipping'];
        case 'completed': return ['text' => 'สำเร็จแล้ว', 'color' => 'bg-green-100 text-green-600 border-green-200', 'icon' => 'check_circle'];
        case 'cancelled': return ['text' => 'ยกเลิกแล้ว', 'color' => 'bg-red-100 text-red-600 border-red-200', 'icon' => 'cancel'];
        default: return ['text' => 'ไม่ทราบสถานะ', 'color' => 'bg-gray-100 text-gray-600 border-gray-200', 'icon' => 'help_outline'];
    }
}
$badge = getStatusBadge($order['status']);

function getPaymentMethodName($method) {
    $method = strtolower(trim($method ?? ''));
    if ($method == 'credit_card') return 'บัตรเครดิต / เดบิต';
    if ($method == 'cod') return 'ชำระเงินปลายทาง (COD)';
    return 'พร้อมเพย์ / โอนผ่านบัญชีธนาคาร'; // ครอบคลุมค่าว่าง หรือ '0' ที่ผิดพลาดของบิลเก่า
}
function getPaymentMethodIcon($method) {
    $method = strtolower(trim($method ?? ''));
    if ($method == 'credit_card') return 'credit_card';
    if ($method == 'cod') return 'delivery_dining';
    return 'qr_code_2';
}
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>รายละเอียดคำสั่งซื้อ #<?= $order['order_no'] ?> - Lumina Beauty</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              primary: "#F43F85", secondary: "#FBCFE8", accent: "#A78BFA",
              "background-light": "#FFF5F7", "background-dark": "#1F1B24",
              "card-light": "#FFFFFF", "card-dark": "#2D2635",
              "text-light": "#374151", "text-dark": "#E5E7EB",
            },
            fontFamily: { display: ["Prompt", "sans-serif"], body: ["Prompt", "sans-serif"] },
            borderRadius: { DEFAULT: "1.5rem", 'xl': '1rem', '2xl': '1.5rem', '3xl': '2rem' },
            boxShadow: { 'soft': '0 10px 40px -10px rgba(244, 63, 133, 0.15)', 'glow': '0 0 20px rgba(244, 63, 133, 0.3)' }
          },
        },
      };
    </script>
<style>
        body { font-family: 'Prompt', sans-serif; }
        .glass-panel { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.5); }
        .dark .glass-panel { background: rgba(45, 38, 53, 0.7); border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        
        /* แอนิเมชันก้อนเมฆจากหน้า Home */
        .cloud-gradient {
            background: radial-gradient(circle at 10% 20%, rgba(244, 63, 133, 0.05) 0%, transparent 30%),
                        radial-gradient(circle at 90% 80%, rgba(14, 165, 233, 0.05) 0%, transparent 30%);
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark transition-colors duration-300 min-h-screen relative overflow-x-hidden cloud-gradient">

<div class="fixed top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
    <div class="absolute -top-[10%] -left-[10%] w-[50%] h-[50%] rounded-full bg-pink-200 dark:bg-pink-900 blur-3xl opacity-30 animate-pulse"></div>
    <div class="absolute top-[40%] -right-[10%] w-[40%] h-[40%] rounded-full bg-purple-200 dark:bg-purple-900 blur-3xl opacity-30 animate-pulse" style="animation-delay: 2s;"></div>
    
    <div class="absolute top-[15%] left-[5%] animate-bounce opacity-40 dark:opacity-20 text-white dark:text-gray-600" style="animation-duration: 4s;">
        <svg width="110" height="65" viewBox="0 0 100 60" fill="currentColor"><path d="M25,50 C11.19,50 0,38.81 0,25 C0,11.19 11.19,0 25,0 C30.68,0 35.91,1.88 40.16,5.09 C43.83,2.02 48.55,0 53.75,0 C66.18,0 76.25,10.07 76.25,22.5 C76.25,23.36 76.19,24.21 76.08,25.04 C80.64,22.45 85.92,21.25 91.25,21.25 C101.6,21.25 110,29.65 110,40 C110,50.35 101.6,58.75 91.25,58.75 L25,58.75 L25,50 Z"/></svg>
    </div>
    <div class="absolute bottom-[20%] right-[10%] animate-bounce opacity-40 dark:opacity-20 text-pink-200 dark:text-pink-900" style="animation-duration: 5s;">
        <svg width="130" height="75" viewBox="0 0 120 70" fill="currentColor"><path d="M30,60 C13.43,60 0,46.57 0,30 C0,13.43 13.43,0 30,0 C36.82,0 43.09,2.26 48.19,6.11 C52.59,2.42 58.26,0 64.5,0 C79.41,0 91.5,12.09 91.5,27 C91.5,28.03 91.43,29.05 91.3,30.05 C96.77,26.94 103.1,25.5 109.5,25.5 C121.93,25.5 132,35.57 132,48 C132,60.43 121.93,70.5 109.5,70.5 L30,70.5 L30,60 Z"/></svg>
    </div>
</div>

<nav class="sticky top-0 z-50 glass-panel shadow-sm px-6 py-4 mb-8 relative z-50">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
        <a href="../home.php" class="flex items-center space-x-2 cursor-pointer hover:opacity-80 transition-opacity">
            <span class="material-icons-round text-primary text-4xl">spa</span>
            <span class="font-bold text-2xl tracking-tight text-primary font-display hidden sm:block">Lumina</span>
        </a>
        <div class="flex items-center space-x-2 sm:space-x-2">
            <a href="../shop/cart.php" class="hover:text-primary transition relative flex items-center">
                <span class="material-icons-round text-2xl">shopping_bag</span>
                <span class="absolute -top-1.5 -right-2 bg-primary text-white text-[10px] font-bold rounded-full h-[18px] w-[18px] flex items-center justify-center border-2 border-white dark:border-gray-800"><?= $totalCartItems ?></span>
            </a>
            <button class="w-10 h-10 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-800 rounded-full transition-all" onclick="toggleTheme()">
                <span class="material-icons-round dark:hidden text-2xl">dark_mode</span>
                <span class="material-icons-round hidden dark:block text-yellow-400 text-2xl">light_mode</span>
            </button>
            <a href="account.php" class="block w-10 h-10 rounded-full bg-gradient-to-tr from-pink-300 to-purple-300 p-0.5 shadow-sm hover:shadow-md hover:scale-105 transition-all cursor-pointer">
                <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-full h-full overflow-hidden">
                    <img alt="Profile" class="w-full h-full rounded-full object-cover" src="<?= htmlspecialchars($profileImage) ?>"/>
                </div>
            </a>
        </div>
    </div>
</nav>

<main class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pb-20">
    
    <div class="flex flex-col mb-6 gap-3">
        <a href="orders.php" class="inline-flex items-center gap-1 text-sm font-bold text-gray-500 hover:text-primary transition w-fit">
            <span class="material-icons-round text-[18px]">arrow_back</span> ย้อนกลับไปประวัติการสั่งซื้อ
        </a>
        <div>
            <div class="text-sm font-medium text-gray-500 mb-1 flex items-center gap-2">
                <a href="account.php" class="hover:text-primary transition">บัญชีของฉัน</a>
                <span class="material-icons-round text-[14px]">chevron_right</span>
                <a href="orders.php" class="hover:text-primary transition">ประวัติการสั่งซื้อ</a>
                <span class="material-icons-round text-[14px]">chevron_right</span>
                <span class="text-primary font-bold">รายละเอียด</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight mb-1">คำสั่งซื้อ #<?= htmlspecialchars($order['order_no']) ?></h1>
            <p class="text-gray-500 dark:text-gray-400 text-sm">สั่งซื้อเมื่อ <?= $formatted_date ?></p>
        </div>
    </div>

    <div class="<?= $badge['color'] ?> border rounded-[2rem] p-6 mb-8 flex items-center gap-4 shadow-sm">
        <div class="w-14 h-14 bg-white/50 rounded-full flex items-center justify-center shrink-0">
            <span class="material-icons-round text-3xl"><?= $badge['icon'] ?></span>
        </div>
        <div>
            <h2 class="text-xl font-bold mb-1">สถานะ: <?= $badge['text'] ?></h2>
            <p class="text-sm opacity-80">
                <?php 
                    if($order['status'] == 'pending') echo "กรุณาชำระเงินเพื่อดำเนินการจัดส่ง";
                    elseif($order['status'] == 'processing') echo "กำลังแพ็คสินค้าและเตรียมมอบให้บริษัทขนส่ง";
                    elseif($order['status'] == 'shipped') echo "สินค้าอยู่ระหว่างการจัดส่ง หมายเลขพัสดุ: <span class='font-bold ml-1'>" . $trackingNo . "</span>";
                    elseif($order['status'] == 'completed') echo "จัดส่งสำเร็จ ขอขอบคุณที่ใช้บริการ Lumina Beauty";
                    elseif($order['status'] == 'cancelled') echo "คำสั่งซื้อถูกยกเลิกแล้ว";
                ?>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-soft border border-pink-50 dark:border-gray-700 p-6 sm:p-8">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2 border-b border-gray-100 dark:border-gray-700 pb-4">รายการสินค้า (<?= count($items) ?> รายการ)</h3>
                
                <div class="space-y-0">
                    <?php foreach ($items as $item): 
                        // 🟢 แก้ที่ 1: ดึงรูปภาพ โดยใช้ file_exists เช็คเหมือน cart.php เป๊ะๆ 🟢
                        // ดักจับถ้า p_image เป็น 0 ให้ไปเอา real_p_image มาแทน
                        $imgName = (!empty($item['p_image']) && $item['p_image'] !== '0') ? $item['p_image'] : ($item['real_p_image'] ?? '');
                        
                        if (!empty($imgName) && file_exists("../uploads/products/" . $imgName)) {
                            $imgUrl = "../uploads/products/" . $imgName;
                        } else {
                            $imgUrl = "https://via.placeholder.com/400x400.png?text=No+Image";
                        }
                    ?>
                        <div class="flex gap-4 items-start sm:items-center py-5 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <div class="w-20 h-20 rounded-3xl bg-white border border-gray-200 dark:border-gray-600 overflow-hidden flex-shrink-0 p-1 shadow-sm">
                                <img src="<?= htmlspecialchars($imgUrl) ?>" class="w-full h-full object-cover rounded-2xl">
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-gray-900 dark:text-white text-sm md:text-base leading-tight mb-1"><?= htmlspecialchars($item['p_name']) ?></h4>
                                
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mb-0.5">หมวดหมู่: <?= htmlspecialchars($item['c_name'] ?? 'ไม่ระบุ') ?></p>
                                
                                <?php if (!empty($item['selected_color'])): ?>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mb-0.5">สี: <?= htmlspecialchars($item['selected_color']) ?></p>
                                <?php endif; ?>
                                
                                <div class="flex justify-between items-end mt-2">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">฿<?= number_format($item['price']) ?> <span class="mx-1">x</span> <?= $item['quantity'] ?></span>
                                    <span class="text-lg font-bold text-primary">฿<?= number_format($item['price'] * $item['quantity']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="lg:col-span-1 space-y-6">
            
            <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-soft border border-pink-50 dark:border-gray-700 p-6">
                <h3 class="font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-icons-round text-primary text-xl">local_shipping</span> การจัดส่ง
                </h3>
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-100 dark:border-gray-600">
                    <p class="text-sm font-bold text-gray-800 dark:text-white mb-1">จัดส่งแบบ <?= $order['shipping_method'] == 'express' ? 'ด่วน (Express)' : 'ธรรมดา (Standard)' ?></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">หมายเลขพัสดุ: <?= in_array($order['status'], ['shipped', 'completed']) ? "<span class='font-bold text-gray-800 dark:text-white'>".$trackingNo."</span>" : "รอการอัปเดต" ?></p>
                    
                    <div class="border-t border-gray-200 dark:border-gray-600 pt-3 mt-3">
                        <p class="text-xs font-bold text-gray-500 mb-1">ที่อยู่จัดส่ง</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed"><?= htmlspecialchars($displayAddress) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-soft border border-pink-50 dark:border-gray-700 p-6">
                <h3 class="font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-icons-round text-primary text-xl">account_balance_wallet</span> วิธีชำระเงิน
                </h3>
                
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-100 dark:border-gray-600 flex items-center gap-3">
                    <span class="material-icons-round text-gray-400">
                        <?= getPaymentMethodIcon($order['payment_method']) ?>
                    </span>
                    <span class="text-sm font-bold text-gray-700 dark:text-gray-300">
                        <?= getPaymentMethodName($order['payment_method']) ?>
                    </span>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-soft border border-pink-50 dark:border-gray-700 p-6">
                <h3 class="font-bold text-gray-900 dark:text-white mb-4">สรุปยอดรวม</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between text-gray-500 dark:text-gray-400">
                        <span>ค่าสินค้า (<?= count($items) ?> ชิ้น)</span>
                        <span class="font-medium text-gray-800 dark:text-white">฿<?= number_format($subtotal) ?></span>
                    </div>
                    
                    <div class="flex justify-between text-gray-500 dark:text-gray-400">
                        <span>ส่วนลดโค้ด</span>
                        <span class="font-bold text-primary">- ฿0</span>
                    </div>

                    <div class="flex justify-between text-gray-500 dark:text-gray-400">
                        <span>ค่าจัดส่ง</span>
                        <?php 
                            $shippingCostCalc = $order['total_amount'] - $subtotal;
                        ?>
                        <span class="font-medium text-gray-800 dark:text-white">
                            <?= $shippingCostCalc > 0 ? '฿'.number_format($shippingCostCalc) : '<span class="text-green-500 font-bold">ฟรี</span>' ?>
                        </span>
                    </div>
                    
                    <div class="border-t border-dashed border-gray-200 dark:border-gray-600 pt-4 mt-2">
                        <div class="flex justify-between items-end">
                            <span class="font-bold text-gray-900 dark:text-white text-base">ยอดสุทธิ</span>
                            <span class="text-2xl font-extrabold text-primary">฿<?= number_format($order['total_amount']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3 mt-6 pt-6 border-t border-gray-100 dark:border-gray-700 flex flex-col w-full">
                    
                    <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
                        <form action="" method="POST" id="cancelForm" class="w-full m-0">
                            <input type="hidden" name="action" value="cancel_order">
                            <input type="hidden" name="order_id" value="<?= $order_id ?>">
                            <button type="button" onclick="confirmCancel()" class="w-full flex items-center justify-center gap-2 px-6 py-3.5 bg-white dark:bg-gray-800 border-2 border-red-500 text-red-500 rounded-xl font-bold hover:bg-red-50 dark:hover:bg-red-900/20 transition shadow-sm">
                                <span class="material-icons-round text-lg">cancel</span> ยกเลิกคำสั่งซื้อ
                            </button>
                        </form>
                    <?php elseif ($order['status'] === 'completed'): ?>
                        <form action="" method="POST" id="refundForm" class="w-full m-0">
                            <input type="hidden" name="action" value="refund_order">
                            <input type="hidden" name="order_id" value="<?= $order_id ?>">
                            <button type="button" onclick="confirmRefund()" class="w-full flex items-center justify-center gap-2 px-6 py-3.5 bg-white dark:bg-gray-800 border-2 border-orange-500 text-orange-500 rounded-xl font-bold hover:bg-orange-50 dark:hover:bg-orange-900/20 transition shadow-sm">
                                <span class="material-icons-round text-lg">currency_exchange</span> ขอคืนเงิน / คืนสินค้า
                            </button>
                        </form>
                    <?php endif; ?>

                    <a href="../auth/contact.php" class="w-full flex items-center justify-center gap-2 px-6 py-3.5 bg-primary text-white rounded-xl font-bold hover:bg-pink-600 transition shadow-sm">
                        <span class="material-icons-round text-[20px]">support_agent</span> ติดต่อ Admin
                    </a>
                </div>
            </div>

        </div>
    </div>
</main>

<script>
    if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        localStorage.setItem('theme', htmlEl.classList.contains('dark') ? 'dark' : 'light');
    }

    // แจ้งเตือน Toast
    <?php if (isset($_SESSION['toast_msg'])): ?>
        Swal.fire({
            toast: true, position: 'top-end', icon: 'success',
            title: '<?= $_SESSION['toast_msg'] ?>',
            showConfirmButton: false, timer: 2500, customClass: { popup: 'rounded-2xl' }
        });
        <?php unset($_SESSION['toast_msg']); ?>
    <?php endif; ?>

    function confirmCancel() {
        Swal.fire({
            title: 'ยืนยันยกเลิกคำสั่งซื้อ?',
            text: "หากยกเลิกแล้วจะไม่สามารถกู้คืนได้",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#9ca3af',
            confirmButtonText: 'ใช่, ยกเลิกเลย',
            cancelButtonText: 'ปิด',
            customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-full px-6', cancelButton: 'rounded-full px-6' }
        }).then((result) => {
            if (result.isConfirmed) document.getElementById('cancelForm').submit();
        });
    }

    function confirmRefund() {
        Swal.fire({
            title: 'ต้องการขอคืนเงิน/คืนสินค้า?',
            text: "โปรดตรวจสอบนโยบายการคืนสินค้าก่อนกดยืนยัน",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f97316',
            cancelButtonColor: '#9ca3af',
            confirmButtonText: 'ส่งคำขอคืนเงิน',
            cancelButtonText: 'ปิด',
            customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-full px-6', cancelButton: 'rounded-full px-6' }
        }).then((result) => {
            if (result.isConfirmed) document.getElementById('refundForm').submit();
        });
    }
</script>
</body></html>