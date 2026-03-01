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
// 2. จัดการรับค่า POST (เปลี่ยนชำระเงิน, ยกเลิก, คืนเงิน)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $target_order_id = (int)$_POST['order_id'];
    
    if ($_POST['action'] === 'update_payment' && isset($_POST['new_payment_method'])) {
        $new_pm = $_POST['new_payment_method'];
        $updSql = "UPDATE `orders` SET payment_method = ? WHERE order_id = ? AND u_id = ?";
        if ($stmtUpd = mysqli_prepare($conn, $updSql)) {
            mysqli_stmt_bind_param($stmtUpd, "sii", $new_pm, $target_order_id, $u_id);
            mysqli_stmt_execute($stmtUpd);
            mysqli_stmt_close($stmtUpd);
        }
        $_SESSION['toast_msg'] = "เปลี่ยนวิธีการชำระเงินสำเร็จ!";
        header("Location: orders_detail.php?id=" . $target_order_id);
        exit();
    }
    
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
        // จำลองการขอคืนเงิน (อาจสร้างสถานะใหม่เป็น refund_requested)
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

// ดึงรายการสินค้า
$items = [];
$subtotal = 0;
$sqlItems = "SELECT * FROM `order_items` WHERE order_id = ?";
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
// 4. จัดเตรียมข้อมูลสำหรับแสดงผล (วันที่, สถานะ, หมายเลขพัสดุสุ่ม)
// ==========================================
$thai_months = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
$time = strtotime($order['created_at']);
$formatted_date = date('d', $time) . ' ' . $thai_months[date('n', $time)] . ' ' . (date('Y', $time) + 543) . ' • ' . date('H:i', $time) . ' น.';

// สร้างเลขพัสดุแบบสุ่มแต่คงที่สำหรับออเดอร์นี้ (โดยใช้ ID มาคำนวณ)
$trackingNo = 'TH' . str_pad(($order_id * 889977) % 10000000000, 10, '0', STR_PAD_LEFT) . 'TH';

// คำนวณเวลาว่าเกิน 1 วันหรือยัง (เพื่อโชว์ปุ่มเปลี่ยนการชำระเงิน)
$hours_passed = (time() - $time) / 3600;
$can_change_payment = ($hours_passed <= 24) && in_array($order['status'], ['pending', 'processing']);

// ฟังก์ชันแปลงสถานะ
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
    if ($method == 'credit_card') return 'บัตรเครดิต / เดบิต';
    if ($method == 'cod') return 'ชำระเงินปลายทาง (COD)';
    return 'พร้อมเพย์ / โอนผ่านบัญชีธนาคาร';
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
    
    <div class="flex justify-between items-start mb-6">
        <div>
            <div class="text-sm font-medium text-gray-500 mb-2 flex items-center gap-2">
                <a href="account.php" class="hover:text-primary transition">บัญชีของฉัน</a>
                <span class="material-icons-round text-[14px]">chevron_right</span>
                <a href="orders.php" class="hover:text-primary transition">ประวัติการสั่งซื้อ</a>
                <span class="material-icons-round text-[14px]">chevron_right</span>
                <span class="text-primary font-bold">รายละเอียด</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight mb-2">คำสั่งซื้อ #<?= htmlspecialchars($order['order_no']) ?></h1>
            <p class="text-gray-500 dark:text-gray-400 text-sm">สั่งซื้อเมื่อ <?= $formatted_date ?></p>
        </div>
        
        <a href="orders.php" class="hidden sm:flex items-center gap-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-4 py-2 rounded-full text-sm font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition shadow-sm">
            <span class="material-icons-round text-[18px]">arrow_back</span> ย้อนกลับ
        </a>
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
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6 border-b border-gray-100 dark:border-gray-700 pb-4">รายการสินค้า (<?= count($items) ?> รายการ)</h3>
                
                <div class="space-y-6">
                    <?php foreach ($items as $item): 
                        $imgUrl = (!empty($item['p_image'])) 
                                    ? "../uploads/products/" . $item['p_image'] 
                                    : "https://via.placeholder.com/150";
                    ?>
                        <div class="flex gap-4 items-start sm:items-center">
                            <div class="w-24 h-24 rounded-2xl bg-gray-50 dark:bg-gray-700 overflow-hidden flex-shrink-0 border border-gray-100 dark:border-gray-600 relative">
                                <img src="<?= htmlspecialchars($imgUrl) ?>" class="w-full h-full object-cover">
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-gray-800 dark:text-white text-base leading-tight mb-1"><?= htmlspecialchars($item['p_name']) ?></h4>
                                
                                <?php if (!empty($item['selected_color'])): ?>
                                    <p class="text-xs font-bold text-primary bg-pink-50 dark:bg-gray-700 w-fit px-2 py-0.5 rounded-md mt-1 mb-2 border border-pink-100 dark:border-gray-600">สี: <?= htmlspecialchars($item['selected_color']) ?></p>
                                <?php endif; ?>
                                
                                <div class="flex justify-between items-end mt-1">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">฿<?= number_format($item['price']) ?> <span class="mx-1">x</span> <?= $item['quantity'] ?></span>
                                    <span class="text-lg font-bold text-primary">฿<?= number_format($item['price'] * $item['quantity']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="flex justify-end gap-4 mt-4">
                <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
                    <form action="" method="POST" id="cancelForm">
                        <input type="hidden" name="action" value="cancel_order">
                        <input type="hidden" name="order_id" value="<?= $order_id ?>">
                        <button type="button" onclick="confirmCancel()" class="px-6 py-3 bg-white dark:bg-gray-800 border-2 border-red-500 text-red-500 rounded-full font-bold hover:bg-red-50 dark:hover:bg-red-900/20 transition shadow-sm">
                            ยกเลิกคำสั่งซื้อ
                        </button>
                    </form>
                <?php elseif ($order['status'] === 'completed'): ?>
                    <form action="" method="POST" id="refundForm">
                        <input type="hidden" name="action" value="refund_order">
                        <input type="hidden" name="order_id" value="<?= $order_id ?>">
                        <button type="button" onclick="confirmRefund()" class="px-6 py-3 bg-white dark:bg-gray-800 border-2 border-orange-500 text-orange-500 rounded-full font-bold hover:bg-orange-50 dark:hover:bg-orange-900/20 transition shadow-sm">
                            ขอคืนเงิน / คืนสินค้า
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="lg:col-span-1 space-y-6">
            
            <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-soft border border-pink-50 dark:border-gray-700 p-6">
                <h3 class="font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-icons-round text-primary text-xl">local_shipping</span> การจัดส่ง
                </h3>
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-100 dark:border-gray-600">
                    <p class="text-sm font-bold text-gray-800 dark:text-white mb-1">จัดส่งแบบ <?= $order['shipping_method'] == 'express' ? 'ด่วน (Express)' : 'ธรรมดา (Standard)' ?></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">หมายเลขพัสดุ: <?= $order['status'] == 'shipped' || $order['status'] == 'completed' ? "<span class='font-bold text-gray-800 dark:text-white'>".$trackingNo."</span>" : "รอการอัปเดต" ?></p>
                    
                    <div class="border-t border-gray-200 dark:border-gray-600 pt-3 mt-3">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">ที่อยู่จัดส่ง</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed"><?= htmlspecialchars($order['shipping_address'] ?? 'ไม่ระบุที่อยู่') ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-soft border border-pink-50 dark:border-gray-700 p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="material-icons-round text-primary text-xl">account_balance_wallet</span> วิธีชำระเงิน
                    </h3>
                    <?php if ($can_change_payment): ?>
                        <button type="button" onclick="openPaymentModal()" class="text-xs font-bold text-blue-500 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-full transition">เปลี่ยน</button>
                    <?php endif; ?>
                </div>
                
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-100 dark:border-gray-600 flex items-center gap-3">
                    <span class="material-icons-round text-gray-400">
                        <?= $order['payment_method'] == 'credit_card' ? 'credit_card' : ($order['payment_method'] == 'cod' ? 'delivery_dining' : 'qr_code_2') ?>
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
            </div>

        </div>
    </div>
</main>

<?php if ($can_change_payment): ?>
<div id="paymentModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden items-center justify-center opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] w-full max-w-lg overflow-hidden shadow-2xl transform scale-95 transition-transform duration-300 modal-content border border-pink-50 dark:border-gray-700">
        
        <div class="px-8 py-5 flex justify-between items-center border-b border-gray-50 dark:border-gray-700">
            <h2 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <span class="material-icons-round text-primary">edit</span> เปลี่ยนวิธีการชำระเงิน
            </h2>
            <button type="button" onclick="closePaymentModal()" class="text-gray-400 hover:text-gray-700 transition-colors">
                <span class="material-icons-round text-xl">close</span>
            </button>
        </div>
        
        <form action="" method="POST" class="p-8 pt-6 pb-8 bg-white dark:bg-gray-800 space-y-3">
            <input type="hidden" name="action" value="update_payment">
            <input type="hidden" name="order_id" value="<?= $order_id ?>">
            
            <label class="relative flex cursor-pointer rounded-2xl border-2 <?= $order['payment_method'] == 'promptpay' ? 'border-primary bg-pink-50/30' : 'border-gray-100' ?> p-4 transition-all">
                <input <?= $order['payment_method'] == 'promptpay' ? 'checked' : '' ?> class="sr-only" name="new_payment_method" type="radio" value="promptpay" onclick="stylePaymentRadios(this)"/>
                <div class="flex h-5 items-center gap-3 w-full">
                    <div class="radio-indicator size-5 rounded-full border-[5px] <?= $order['payment_method'] == 'promptpay' ? 'border-primary' : 'border-gray-300 border-2' ?> bg-white"></div>
                    <span class="material-icons-round text-primary">qr_code_2</span>
                    <span class="font-bold text-gray-900 dark:text-white">พร้อมเพย์ / โอนเงิน</span>
                </div>
            </label>
            
            <label class="relative flex cursor-pointer rounded-2xl border-2 <?= $order['payment_method'] == 'credit_card' ? 'border-primary bg-pink-50/30' : 'border-gray-100' ?> p-4 transition-all">
                <input <?= $order['payment_method'] == 'credit_card' ? 'checked' : '' ?> class="sr-only" name="new_payment_method" type="radio" value="credit_card" onclick="stylePaymentRadios(this)"/>
                <div class="flex h-5 items-center gap-3 w-full">
                    <div class="radio-indicator size-5 rounded-full border-[5px] <?= $order['payment_method'] == 'credit_card' ? 'border-primary' : 'border-gray-300 border-2' ?> bg-white"></div>
                    <span class="material-icons-round text-gray-500">credit_card</span>
                    <span class="font-bold text-gray-900 dark:text-white">บัตรเครดิต / เดบิต</span>
                </div>
            </label>
            
            <label class="relative flex cursor-pointer rounded-2xl border-2 <?= $order['payment_method'] == 'cod' ? 'border-primary bg-pink-50/30' : 'border-gray-100' ?> p-4 transition-all">
                <input <?= $order['payment_method'] == 'cod' ? 'checked' : '' ?> class="sr-only" name="new_payment_method" type="radio" value="cod" onclick="stylePaymentRadios(this)"/>
                <div class="flex h-5 items-center gap-3 w-full">
                    <div class="radio-indicator size-5 rounded-full border-[5px] <?= $order['payment_method'] == 'cod' ? 'border-primary' : 'border-gray-300 border-2' ?> bg-white"></div>
                    <span class="material-icons-round text-gray-500">delivery_dining</span>
                    <span class="font-bold text-gray-900 dark:text-white">ชำระเงินปลายทาง (COD)</span>
                </div>
            </label>

            <div class="mt-8 flex justify-between gap-4 pt-4">
                <button type="button" onclick="closePaymentModal()" class="flex-1 py-3.5 bg-gray-100 text-gray-700 font-bold rounded-2xl hover:bg-gray-200 transition">ยกเลิก</button>
                <button type="submit" class="flex-1 py-3.5 bg-primary text-white font-bold rounded-2xl hover:bg-pink-600 transition shadow-lg shadow-primary/30">บันทึก</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

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

    // Modal เปลี่ยนชำระเงิน
    const paymentModal = document.getElementById('paymentModal');
    function openPaymentModal() {
        if(!paymentModal) return;
        paymentModal.classList.remove('hidden'); paymentModal.classList.add('flex');
        setTimeout(() => { paymentModal.classList.remove('opacity-0'); paymentModal.querySelector('.modal-content').classList.remove('scale-95'); }, 10);
    }
    function closePaymentModal() {
        if(!paymentModal) return;
        paymentModal.classList.add('opacity-0'); paymentModal.querySelector('.modal-content').classList.add('scale-95');
        setTimeout(() => { paymentModal.classList.add('hidden'); paymentModal.classList.remove('flex'); }, 300);
    }
    function stylePaymentRadios(selectedInput) {
        const allLabels = paymentModal.querySelectorAll('label');
        allLabels.forEach(lbl => {
            const ind = lbl.querySelector('.radio-indicator');
            lbl.classList.remove('border-primary', 'bg-pink-50/30'); lbl.classList.add('border-gray-100');
            ind.classList.remove('border-primary', 'border-[5px]'); ind.classList.add('border-gray-300', 'border-2');
        });
        const labelElement = selectedInput.closest('label');
        labelElement.classList.add('border-primary', 'bg-pink-50/30'); labelElement.classList.remove('border-gray-100');
        const indicator = labelElement.querySelector('.radio-indicator');
        indicator.classList.add('border-primary', 'border-[5px]'); indicator.classList.remove('border-gray-300', 'border-2');
    }
</script>
</body></html>