<?php
session_start();
require_once '../config/connectdbuser.php';

// ==========================================
// 1. ตรวจสอบสิทธิ์ (จำลอง Admin)
// ==========================================
// if (!isset($_SESSION['admin_id'])) { header("Location: ../auth/login.php"); exit(); }
$adminName = $_SESSION['admin_username'] ?? 'Admin Nina'; 
$adminAvatar = "https://ui-avatars.com/api/?name=Admin&background=a855f7&color=fff";

// ==========================================
// 2. จัดการแก้ไขสถานะ (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    
    $sqlUp = "UPDATE `orders` SET status = ? WHERE order_id = ?";
    if ($stmtUp = mysqli_prepare($conn, $sqlUp)) {
        mysqli_stmt_bind_param($stmtUp, "si", $new_status, $order_id);
        mysqli_stmt_execute($stmtUp);
        $_SESSION['success_msg'] = "อัปเดตสถานะออเดอร์สำเร็จ!";
    }
    header("Location: manage_orders.php");
    exit();
}

// ==========================================
// 3. จัดการลบออเดอร์ (GET)
// ==========================================
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    mysqli_query($conn, "DELETE FROM `order_items` WHERE order_id = $del_id");
    mysqli_query($conn, "DELETE FROM `orders` WHERE order_id = $del_id");
    
    $_SESSION['success_msg'] = "ลบรายการสั่งซื้อเรียบร้อยแล้ว!";
    header("Location: manage_orders.php");
    exit();
}

// ==========================================
// 4. ตัวกรองสถานะ และ ระบบค้นหา (GET)
// ==========================================
$current_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$whereClauseArr = [];

if ($current_filter !== 'all') {
    $whereClauseArr[] = "o.status = '" . mysqli_real_escape_string($conn, $current_filter) . "'";
}

if ($search !== '') {
    $searchEsc = mysqli_real_escape_string($conn, $search);
    $whereClauseArr[] = "(o.order_no LIKE '%$searchEsc%' OR a.u_username LIKE '%$searchEsc%' OR a.u_name LIKE '%$searchEsc%')";
}

$whereClause = count($whereClauseArr) > 0 ? "WHERE " . implode(" AND ", $whereClauseArr) : "";

// ==========================================
// 5. ดึงข้อมูลออเดอร์ทั้งหมด
// ==========================================
$orders = [];
$sqlOrders = "
    SELECT o.*, a.u_username, a.u_name, u.u_phone, u.u_image,
           (SELECT CONCAT(address_line, ' ', district, ' ', province, ' ', zipcode) 
            FROM user_address WHERE u_id = o.u_id ORDER BY is_default DESC LIMIT 1) as u_address
    FROM `orders` o 
    LEFT JOIN `account` a ON o.u_id = a.u_id 
    LEFT JOIN `user` u ON o.u_id = u.u_id
    $whereClause
    ORDER BY o.created_at DESC
";

if ($resOrders = mysqli_query($conn, $sqlOrders)) {
    while ($row = mysqli_fetch_assoc($resOrders)) {
        $o_id = $row['order_id'];
        
        $items = [];
        $totalQty = 0; // 🟢 นับจำนวนชิ้นทั้งหมด
        $sqlItems = "SELECT * FROM `order_items` WHERE order_id = $o_id";
        $resItems = mysqli_query($conn, $sqlItems);
        while ($item = mysqli_fetch_assoc($resItems)) {
            $totalQty += (int)$item['quantity'];
            $items[] = $item;
        }
        
        $row['items'] = $items;
        $row['total_qty'] = $totalQty;
        $orders[] = $row;
    }
}

// นับจำนวนตามสถานะ
$countAll = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders`"))['c'] ?? 0;
$countPending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders` WHERE status='pending'"))['c'] ?? 0;
$countProcessing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders` WHERE status='processing'"))['c'] ?? 0;
$countShipped = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders` WHERE status='shipped'"))['c'] ?? 0;
$countCompleted = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders` WHERE status='completed'"))['c'] ?? 0;

$newOrders = $countPending; // โชว์ใน Sidebar
$newComplaints = 0; // ตัวแปรจำลองตามหน้า dashboard

function getStatusBadge($status) {
    $badges = [
        'pending' => ['text' => 'รอชำระเงิน', 'class' => 'bg-orange-100 text-orange-600 border-orange-200 dark:bg-orange-900/30 dark:border-orange-800', 'icon' => 'schedule'],
        'processing' => ['text' => 'กำลังจัดส่ง', 'class' => 'bg-blue-100 text-blue-600 border-blue-200 dark:bg-blue-900/30 dark:border-blue-800', 'icon' => 'inventory_2'],
        'shipped' => ['text' => 'จัดส่งแล้ว', 'class' => 'bg-purple-100 text-purple-600 border-purple-200 dark:bg-purple-900/30 dark:border-purple-800', 'icon' => 'local_shipping'],
        'completed' => ['text' => 'สำเร็จแล้ว', 'class' => 'bg-green-100 text-green-600 border-green-200 dark:bg-green-900/30 dark:border-green-800', 'icon' => 'check_circle'],
        'cancelled' => ['text' => 'ยกเลิก', 'class' => 'bg-red-100 text-red-600 border-red-200 dark:bg-red-900/30 dark:border-red-800', 'icon' => 'cancel']
    ];
    return $badges[$status] ?? ['text' => 'ไม่ทราบ', 'class' => 'bg-gray-100 text-gray-600 border-gray-200 dark:bg-gray-800 dark:border-gray-700', 'icon' => 'help_outline'];
}

function getPaymentMethodText($method) {
    if ($method == 'promptpay' || $method == 'โอนเงิน') return ['text' => 'โอนเงิน', 'icon' => 'account_balance', 'color' => 'text-blue-500 bg-blue-50 border-blue-100 dark:bg-blue-900/30 dark:border-blue-800'];
    if ($method == 'credit_card') return ['text' => 'บัตรเครดิต', 'icon' => 'credit_card', 'color' => 'text-purple-500 bg-purple-50 border-purple-100 dark:bg-purple-900/30 dark:border-purple-800'];
    if ($method == 'cod') return ['text' => 'COD ปลายทาง', 'icon' => 'local_shipping', 'color' => 'text-orange-500 bg-orange-50 border-orange-100 dark:bg-orange-900/30 dark:border-orange-800'];
    return ['text' => 'ไม่ระบุ', 'icon' => 'payments', 'color' => 'text-gray-500 bg-gray-50 border-gray-100 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400'];
}
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>จัดการรายการสั่งซื้อ - Lumina Admin</title>

<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    tailwind.config = {
        darkMode: "class", // 🟢 เปิดใช้งาน Dark Mode
        theme: {
            extend: {
                colors: {
                    primary: "#ec2d88", 
                    "primary-light": "#fce7f3",
                    secondary: "#a855f7",
                    "background-light": "#fff5f9", 
                    "background-dark": "#1F1B24", 
                    "surface-white": "#ffffff",
                    "surface-dark": "#2D2635", 
                    "text-main": "#1f2937",
                    "text-muted": "#6b7280",
                },
                fontFamily: {
                    sans: ["Prompt", "sans-serif"],
                    display: ["Prompt", "sans-serif"]
                },
                borderRadius: {
                    DEFAULT: "1rem", "lg": "1.5rem", "xl": "2rem", "2xl": "2.5rem"
                },
                boxShadow: {
                    "soft": "0 4px 20px -2px rgba(236, 45, 136, 0.1)",
                    "card": "0 2px 10px -2px rgba(0, 0, 0, 0.05)",
                    "glow": "0 0 15px rgba(236, 45, 136, 0.3)"
                }
            },
        },
    }
</script>
<style>
    body { font-family: 'Prompt', sans-serif; }
    .sidebar-gradient { background: linear-gradient(180deg, #ffffff 0%, #fff5f9 100%); }
    .dark .sidebar-gradient { background: linear-gradient(180deg, #2D2635 0%, #1F1B24 100%); }
    
    .glass-panel { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(236, 45, 136, 0.1); }
    .dark .glass-panel { background: rgba(45, 38, 53, 0.85); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
    
    .nav-item-active { background-color: #ec2d88; color: white; box-shadow: 0 4px 12px rgba(236, 45, 136, 0.3); }
    .nav-item:hover:not(.nav-item-active) { background-color: #fce7f3; color: #ec2d88; }
    .dark .nav-item:hover:not(.nav-item-active) { background-color: rgba(236, 45, 136, 0.2); }
    
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: #FBCFE8; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #ec2d88; }
    .custom-scroll::-webkit-scrollbar { width: 4px; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }
    .dark .custom-scroll::-webkit-scrollbar-thumb { background: #4B5563; }
</style>
</head>
<body class="bg-background-light dark:bg-background-dark font-sans text-text-main dark:text-gray-200 antialiased overflow-x-hidden selection:bg-primary selection:text-white transition-colors duration-300">
<div class="flex min-h-screen w-full">
    
    <aside class="hidden lg:flex flex-col w-72 h-screen sticky top-0 border-r border-pink-100 dark:border-gray-800 sidebar-gradient p-6 justify-between z-20 shadow-sm transition-colors duration-300">
        <div>
            <a href="../home.php" class="flex items-center gap-2 px-2 mb-10 hover:opacity-80 transition-opacity cursor-pointer">
                <span class="material-icons-round text-primary text-4xl">spa</span>
                <span class="font-bold text-2xl tracking-tight text-primary">Lumina</span>
                <span class="text-xs font-bold text-purple-500 bg-purple-100 dark:bg-purple-900/30 px-2 py-0.5 rounded-full ml-1 border border-purple-200 dark:border-purple-800/50">Admin</span>
            </a>
            <nav class="flex flex-col gap-2">
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted dark:text-gray-400 transition-all duration-300 group hover:pl-6" href="dashboard.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">dashboard</span>
                    <span class="font-medium text-[15px]">ภาพรวมระบบ</span>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted dark:text-gray-400 transition-all duration-300 group hover:pl-6" href="manage_products.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">inventory_2</span>
                    <span class="font-medium text-[15px]">จัดการสินค้า</span>
                </a>
                
                <a class="nav-item-active flex items-center justify-between px-5 py-3.5 rounded-2xl transition-all duration-300 group" href="manage_orders.php">
                    <div class="flex items-center gap-4">
                        <span class="material-icons-round">receipt_long</span>
                        <span class="font-bold text-[15px]">รายการสั่งซื้อ</span>
                    </div>
                    <?php if($newOrders > 0): ?>
                        <span class="bg-white text-primary text-[11px] font-extrabold px-2 py-0.5 rounded-full shadow-sm"><?= $newOrders ?></span>
                    <?php endif; ?>
                </a>
                
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted dark:text-gray-400 transition-all duration-300 group hover:pl-6" href="manage_customers.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">group</span>
                    <span class="font-medium text-[15px]">ข้อมูลลูกค้า</span>
                </a>
                
                <a class="nav-item flex items-center justify-between px-5 py-3.5 rounded-2xl text-text-muted dark:text-gray-400 transition-all duration-300 group hover:pl-6" href="manage_complaints.php">
                    <div class="flex items-center gap-4">
                        <span class="material-icons-round group-hover:scale-110 transition-transform">forum</span>
                        <span class="font-medium text-[15px]">คำร้องเรียน</span>
                    </div>
                    <?php if(!empty($newComplaints) && $newComplaints > 0): ?>
                        <span class="bg-primary text-white text-[11px] font-bold px-2 py-0.5 rounded-full shadow-sm"><?= $newComplaints ?></span>
                    <?php endif; ?>
                </a>
                
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted dark:text-gray-400 transition-all duration-300 group hover:pl-6 mt-2" href="settings.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">settings</span>
                    <span class="font-medium text-[15px]">ตั้งค่าระบบ</span>
                </a>
            </nav>
        </div>

        <div class="p-5 rounded-2xl bg-gradient-to-br from-pink-50 to-purple-50 dark:from-gray-800 dark:to-gray-800 border border-pink-100 dark:border-gray-700 flex items-center gap-3 shadow-sm">
            <div class="w-10 h-10 rounded-full bg-white dark:bg-gray-700 flex items-center justify-center text-primary shadow-sm">
                <span class="material-icons-round text-xl">support_agent</span>
            </div>
            <div>
                <p class="text-sm font-bold text-primary dark:text-pink-400">ต้องการความช่วยเหลือ?</p>
                <p class="text-xs text-text-muted dark:text-gray-400">ติดต่อทีมพัฒนาระบบ</p>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0">
        <header class="flex items-center justify-between px-6 py-4 lg:px-10 lg:py-5 glass-panel sticky top-0 z-10 transition-colors duration-300">
            <div class="flex items-center gap-4 lg:hidden">
                <button class="p-2 text-text-main dark:text-white hover:bg-pink-50 dark:hover:bg-gray-800 rounded-xl transition-colors">
                    <span class="material-icons-round">menu</span>
                </button>
                <span class="font-bold text-xl text-primary flex items-center gap-1"><span class="material-icons-round">spa</span> Lumina</span>
            </div>
            
            <form method="GET" action="manage_orders.php" class="hidden md:flex flex-1 max-w-md relative group items-center">
                <input type="hidden" name="status" value="<?= htmlspecialchars($current_filter) ?>">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <span class="material-icons-round text-gray-400 group-focus-within:text-primary transition-colors text-[20px]">search</span>
                </div>
                <input name="search" value="<?= htmlspecialchars($search) ?>" class="block w-full pl-12 pr-10 py-2.5 rounded-full border border-pink-100 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm text-sm placeholder-gray-400 dark:text-white focus:ring-2 focus:ring-primary/20 transition-all outline-none" placeholder="ค้นหาหมายเลขคำสั่งซื้อ, ชื่อลูกค้า..." type="text"/>
                <?php if (!empty($search)): ?>
                    <a href="manage_orders.php?status=<?= htmlspecialchars($current_filter) ?>" class="absolute right-3 w-6 h-6 bg-pink-50 dark:bg-gray-700 hover:bg-red-100 dark:hover:bg-red-900/30 text-primary dark:text-pink-400 hover:text-red-500 rounded-full transition-colors flex items-center justify-center shadow-sm" title="ล้างการค้นหา">
                        <span class="material-icons-round text-[14px]">close</span>
                    </a>
                <?php endif; ?>
                <button type="submit" class="hidden"></button>
            </form>
            
            <div class="flex items-center gap-4 lg:gap-6 ml-auto">
                <button class="w-10 h-10 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-primary dark:hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-800 rounded-full transition-all" onclick="toggleTheme()">
                    <span class="material-icons-round dark:hidden text-2xl">dark_mode</span>
                    <span class="material-icons-round hidden dark:block text-yellow-400 text-2xl">light_mode</span>
                </button>
                
                <div class="relative group flex items-center">
                    <a href="#" class="block w-11 h-11 rounded-full bg-gradient-to-tr from-purple-400 to-indigo-400 p-[2px] shadow-sm hover:shadow-glow hover:scale-105 transition-all cursor-pointer">
                        <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-full h-full overflow-hidden">
                            <img alt="Admin" class="w-full h-full rounded-full object-cover" src="<?= $adminAvatar ?>"/>
                        </div>
                    </a>
                    <div class="absolute right-0 hidden pt-4 top-full w-[300px] z-50 group-hover:block cursor-default">
                        <div class="bg-surface-white dark:bg-surface-dark rounded-3xl shadow-soft border border-pink-100 dark:border-gray-700 overflow-hidden p-5 relative">
                            <div class="text-center mb-4"><span class="text-sm font-bold text-purple-500 bg-purple-50 dark:bg-purple-900/30 px-3 py-1 rounded-full border border-purple-100 dark:border-purple-800/50">Administrator Mode</span></div>
                            <div class="flex justify-center relative mb-3">
                                <div class="rounded-full p-[3px] bg-purple-500 shadow-md">
                                    <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-16 h-16 overflow-hidden"><img src="<?= $adminAvatar ?>" alt="Profile" class="w-full h-full rounded-full object-cover"></div>
                                </div>
                            </div>
                            <div class="text-center mb-6">
                                <h3 class="text-[20px] font-bold text-gray-800 dark:text-white">สวัสดี, คุณ <?= htmlspecialchars($adminName) ?></h3>
                            </div>
                            <div class="flex flex-col gap-3">
                                <a href="../auth/logout.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-red-500 hover:bg-red-500 hover:text-white rounded-full py-2.5 transition text-[14px] font-semibold text-red-500">
                                    <span class="material-icons-round text-[18px]">logout</span> ออกจากระบบ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="p-6 lg:p-8 flex flex-col gap-6 max-w-[1600px] mx-auto w-full">
            
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex flex-col gap-2">
                    <h1 class="text-4xl font-extrabold text-gray-800 dark:text-white tracking-tight flex items-center gap-3">
                        จัดการรายการสั่งซื้อ 
                    </h1>
                    <span class="text-base font-bold bg-pink-100 dark:bg-pink-900/30 border border-pink-200 dark:border-pink-800/50 text-primary dark:text-pink-400 w-fit px-4 py-1.5 rounded-full shadow-sm">
                        ทั้งหมด <?= $countAll ?> รายการ
                    </span>
                </div>
            </div>

            <div class="bg-surface-white dark:bg-surface-dark rounded-full p-2 shadow-sm border border-gray-100 dark:border-gray-700 inline-flex overflow-x-auto custom-scroll w-full sm:w-auto">
                <a href="?status=all<?= $search !== '' ? '&search='.urlencode($search) : '' ?>" class="px-6 py-2.5 rounded-full text-[15px] font-bold whitespace-nowrap transition-all <?= $current_filter == 'all' ? 'bg-primary text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:bg-pink-50 dark:hover:bg-gray-800 hover:text-primary' ?>">
                    ทั้งหมด
                </a>
                <a href="?status=pending<?= $search !== '' ? '&search='.urlencode($search) : '' ?>" class="px-6 py-2.5 rounded-full text-[15px] font-bold whitespace-nowrap transition-all flex items-center gap-2 <?= $current_filter == 'pending' ? 'bg-primary text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:bg-pink-50 dark:hover:bg-gray-800 hover:text-primary' ?>">
                    รอชำระเงิน <?= $countPending > 0 ? '<span class="w-5 h-5 flex items-center justify-center bg-red-500 text-white text-[11px] rounded-full shadow-sm">'.$countPending.'</span>' : '' ?>
                </a>
                <a href="?status=processing<?= $search !== '' ? '&search='.urlencode($search) : '' ?>" class="px-6 py-2.5 rounded-full text-[15px] font-bold whitespace-nowrap transition-all <?= $current_filter == 'processing' ? 'bg-primary text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:bg-pink-50 dark:hover:bg-gray-800 hover:text-primary' ?>">
                    กำลังจัดส่ง (<?= $countProcessing ?>)
                </a>
                <a href="?status=shipped<?= $search !== '' ? '&search='.urlencode($search) : '' ?>" class="px-6 py-2.5 rounded-full text-[15px] font-bold whitespace-nowrap transition-all <?= $current_filter == 'shipped' ? 'bg-primary text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:bg-pink-50 dark:hover:bg-gray-800 hover:text-primary' ?>">
                    จัดส่งแล้ว (<?= $countShipped ?>)
                </a>
                <a href="?status=completed<?= $search !== '' ? '&search='.urlencode($search) : '' ?>" class="px-6 py-2.5 rounded-full text-[15px] font-bold whitespace-nowrap transition-all <?= $current_filter == 'completed' ? 'bg-primary text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:bg-pink-50 dark:hover:bg-gray-800 hover:text-primary' ?>">
                    สำเร็จแล้ว (<?= $countCompleted ?>)
                </a>
            </div>

            <div class="bg-surface-white dark:bg-surface-dark rounded-[2.5rem] shadow-card overflow-hidden border border-gray-100 dark:border-gray-700 relative">
                <div class="overflow-x-auto min-h-[400px]">
                    <table class="w-full text-left border-collapse min-w-[1000px]">
                        <thead>
                            <tr class="bg-gray-50/80 dark:bg-gray-800/50 text-gray-600 dark:text-gray-400 text-[15px] uppercase tracking-wider border-b border-gray-100 dark:border-gray-700">
                                <th class="px-6 py-5 font-bold pl-8">หมายเลขคำสั่งซื้อ</th>
                                <th class="px-6 py-5 font-bold">ชื่อลูกค้า</th>
                                <th class="px-6 py-5 font-bold text-center">วันที่สั่งซื้อ</th>
                                <th class="px-6 py-5 font-bold text-center">ยอดรวมสุทธิ</th>
                                <th class="px-6 py-5 font-bold text-center">ช่องทางการชำระเงิน</th>
                                <th class="px-6 py-5 font-bold text-center">สถานะ</th>
                                <th class="px-6 py-5 font-bold text-center pr-8">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-20 text-center text-gray-400 dark:text-gray-500">
                                        <div class="flex flex-col items-center justify-center">
                                            <span class="material-icons-round text-7xl text-gray-200 dark:text-gray-600 mb-4">inbox</span>
                                            <p class="text-lg font-medium">ไม่พบรายการสั่งซื้อในสถานะนี้</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($orders as $o): 
                                    $badge = getStatusBadge($o['status']);
                                    $payInfo = getPaymentMethodText($o['payment_method'] ?? 'ไม่ระบุ');
                                    $dateStr = date('d M Y, H:i', strtotime($o['created_at']));
                                    
                                    // 🟢 แก้ที่ 6: รูปโปรไฟล์จริง ดึงจาก profile/uploads 🟢
                                    $imgUrlTable = (!empty($o['u_image']) && file_exists("../profile/uploads/" . $o['u_image'])) 
                                        ? "../profile/uploads/" . $o['u_image'] 
                                        : "https://ui-avatars.com/api/?name=" . urlencode($o['u_name'] ?? $o['u_username'] ?? 'U') . "&background=fce7f3&color=ec2d88";
                                    
                                    $o_json = htmlspecialchars(json_encode($o), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="hover:bg-pink-50/30 dark:hover:bg-gray-800/30 transition-colors group">
                                    <td class="px-6 py-5 pl-8">
                                        <div class="flex flex-col">
                                            <span class="font-extrabold text-gray-900 dark:text-white text-lg group-hover:text-primary transition-colors">#<?= htmlspecialchars($o['order_no']) ?></span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium"><?= $o['total_qty'] ?> ชิ้น</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 rounded-full overflow-hidden shadow-sm border border-gray-100 dark:border-gray-600 flex-shrink-0 bg-white dark:bg-gray-800">
                                                <img src="<?= $imgUrlTable ?>" class="w-full h-full object-cover">
                                            </div>
                                            <span class="font-bold text-gray-800 dark:text-gray-200 text-base"><?= htmlspecialchars($o['u_name'] ?? $o['u_username'] ?? 'ลูกค้าทั่วไป') ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-gray-500 dark:text-gray-400 font-medium text-base text-center">
                                        <?= $dateStr ?>
                                    </td>
                                    <td class="px-6 py-5 font-black text-primary text-xl text-center">
                                        ฿<?= number_format($o['total_amount'], 2) ?>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <div class="inline-flex items-center justify-center gap-2 <?= $payInfo['color'] ?> px-4 py-2 rounded-xl border shadow-sm w-fit mx-auto">
                                            <span class="material-icons-round text-[20px]"><?= $payInfo['icon'] ?></span>
                                            <span class="font-bold text-[14px]"><?= $payInfo['text'] ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-bold border shadow-sm <?= $badge['class'] ?>">
                                            <span class="material-icons-round text-[18px]"><?= $badge['icon'] ?></span> <?= $badge['text'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-5 pr-8 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick="openOrderModal('<?= $o_json ?>')" class="w-11 h-11 rounded-full text-gray-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-gray-700 transition-colors flex items-center justify-center shadow-sm border border-gray-100 dark:border-gray-600" title="ดูรายละเอียด">
                                                <span class="material-icons-round text-[24px]">visibility</span>
                                            </button>
                                            <button onclick="confirmDelete(<?= $o['order_id'] ?>)" class="w-11 h-11 rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-gray-700 transition-colors flex items-center justify-center shadow-sm border border-gray-100 dark:border-gray-600" title="ลบรายการ">
                                                <span class="material-icons-round text-[24px]">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<div id="orderModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden items-center justify-center opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-surface-white dark:bg-surface-dark rounded-[2.5rem] w-full max-w-5xl h-auto max-h-[95vh] shadow-2xl flex flex-col overflow-hidden modal-content transform scale-95 transition-transform duration-300 border border-gray-100 dark:border-gray-700 relative">
        
        <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800/50">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-white dark:bg-gray-700 rounded-full flex items-center justify-center text-primary shadow-sm border border-gray-200 dark:border-gray-600">
                    <span class="material-icons-round text-3xl">local_mall</span>
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white flex items-center gap-3">
                        คำสั่งซื้อ <span id="md_order_no" class="text-primary">#ORD-XXXX</span>
                    </h2>
                    <div class="flex items-center gap-3 mt-1.5">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            <span class="material-icons-round text-[16px]">schedule</span> <span id="md_date">XX X.X. XXXX, XX:XX น.</span>
                        </p>
                        <span id="md_status_badge" class="px-3 py-0.5 rounded-full text-xs font-bold border shadow-sm"></span>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <form action="" method="POST" class="flex items-center gap-2 m-0 bg-white dark:bg-gray-800 p-1.5 rounded-full border border-gray-200 dark:border-gray-600 shadow-sm">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" id="md_form_order_id" value="">
                    <select name="status" id="md_status_select" class="text-[15px] font-bold text-gray-700 dark:text-gray-200 bg-transparent border-none focus:ring-0 cursor-pointer py-1.5 pl-4 pr-10 outline-none">
                        <option value="pending" class="text-gray-900">รอชำระเงิน</option>
                        <option value="processing" class="text-gray-900">กำลังจัดส่ง</option>
                        <option value="shipped" class="text-gray-900">จัดส่งแล้ว</option>
                        <option value="completed" class="text-gray-900">สำเร็จแล้ว</option>
                        <option value="cancelled" class="text-gray-900">ยกเลิก</option>
                    </select>
                    <button type="submit" class="bg-primary hover:bg-pink-600 text-white px-5 py-2 rounded-full text-sm font-bold transition-colors shadow-sm">อัปเดต</button>
                </form>

                <button type="button" onclick="closeOrderModal()" class="w-12 h-12 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 shadow-sm transition-colors">
                    <span class="material-icons-round text-[24px]">close</span>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto custom-scroll p-8 bg-gray-50/50 dark:bg-gray-900/50">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <div class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] p-8 shadow-sm border border-gray-100 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-5 flex items-center gap-2"><span class="material-icons-round text-primary">shopping_bag</span> รายการสินค้า</h3>
                        <div id="md_items_container" class="space-y-5 max-h-[300px] overflow-y-auto pr-2 custom-scroll">
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] p-8 shadow-sm border border-gray-100 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-5 flex items-center gap-2"><span class="material-icons-round text-primary">payments</span> การชำระเงิน & จัดส่ง</h3>
                        <div class="flex gap-6 items-start">
                            <div class="w-28 h-36 bg-gray-50 dark:bg-gray-700 rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-600 flex items-center justify-center flex-shrink-0 cursor-pointer hover:opacity-80 transition shadow-inner" onclick="window.open(document.getElementById('md_slip_img').src, '_blank')">
                                <img id="md_slip_img" src="" alt="Slip" class="w-full h-full object-cover hidden">
                                <span id="md_slip_none" class="text-xs text-gray-400 font-medium text-center px-2 leading-relaxed">ไม่มีรูปสลิป<br>ไม่ได้โอน</span>
                            </div>
                            <div class="space-y-4 flex-1">
                                <div>
                                    <p class="text-sm font-bold text-gray-500 dark:text-gray-400 mb-1.5">ช่องทางชำระเงิน:</p>
                                    <div class="font-bold text-base px-4 py-2 rounded-xl inline-block border shadow-sm" id="md_pay_method"></div>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-gray-500 dark:text-gray-400 mb-1.5">รูปแบบจัดส่ง:</p>
                                    <div class="font-bold text-base px-4 py-2 rounded-xl inline-block border shadow-sm" id="md_shipping_method"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] p-8 shadow-sm border border-gray-100 dark:border-gray-700 relative overflow-hidden">
                        <div class="absolute top-0 right-0 p-4 opacity-5"><span class="material-icons-round text-[100px] text-primary">person</span></div>
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-5 flex items-center gap-2 relative z-10"><span class="material-icons-round text-primary">person</span> ข้อมูลลูกค้า</h3>
                        
                        <div class="flex gap-5 items-center mb-6 relative z-10">
                            <div class="w-16 h-16 rounded-full bg-white dark:bg-gray-700 p-1 border border-gray-200 dark:border-gray-600 shadow-sm flex-shrink-0">
                                <img id="md_cus_image" src="" class="w-full h-full rounded-full object-cover">
                            </div>
                            <div>
                                <p class="font-extrabold text-gray-900 dark:text-white text-xl" id="md_cus_name"></p>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-0.5" id="md_cus_username"></p>
                            </div>
                        </div>

                        <div class="space-y-4 relative z-10 bg-gray-50 dark:bg-gray-700/50 p-5 rounded-2xl border border-gray-100 dark:border-gray-600">
                            <div>
                                <p class="text-sm font-bold text-gray-500 dark:text-gray-400 mb-1">เบอร์โทรศัพท์</p>
                                <p class="font-bold text-gray-800 dark:text-white text-base flex items-center gap-1.5"><span class="material-icons-round text-[18px] text-gray-400">phone</span> <span id="md_cus_phone"></span></p>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-500 dark:text-gray-400 mb-1">ที่อยู่จัดส่ง</p>
                                <p class="font-medium text-gray-700 dark:text-gray-200 text-base leading-relaxed" id="md_cus_address"></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] p-8 shadow-sm border border-gray-100 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-5 flex items-center gap-2"><span class="material-icons-round text-primary">receipt_long</span> สรุปยอดชำระ</h3>
                        <div class="space-y-3 pb-5 border-b border-dashed border-gray-200 dark:border-gray-600">
                            <div class="flex justify-between text-base">
                                <span class="text-gray-500 dark:text-gray-400 font-medium">ยอดรวมสินค้า</span>
                                <span class="font-bold text-gray-800 dark:text-white" id="md_sum_items">฿0.00</span>
                            </div>
                            <div class="flex justify-between text-base">
                                <span class="text-gray-500 dark:text-gray-400 font-medium">ค่าจัดส่ง</span>
                                <span class="font-bold text-gray-800 dark:text-white" id="md_sum_shipping">฿0.00</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-end pt-5">
                            <span class="font-extrabold text-gray-800 dark:text-white text-lg">ยอดสุทธิ</span>
                            <span class="font-black text-4xl text-primary tracking-tight" id="md_sum_total">฿0.00</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
    function toggleTheme() {
        const htmlEl = document.documentElement; htmlEl.classList.toggle('dark');
        localStorage.setItem('theme', htmlEl.classList.contains('dark') ? 'dark' : 'light');
    }

    const badgesData = {
        'pending': {text: 'รอชำระเงิน', class: 'bg-orange-100 text-orange-600 border-orange-200 dark:bg-orange-900/30 dark:border-orange-800', icon: 'schedule'},
        'processing': {text: 'กำลังจัดส่ง', class: 'bg-blue-100 text-blue-600 border-blue-200 dark:bg-blue-900/30 dark:border-blue-800', icon: 'inventory_2'},
        'shipped': {text: 'จัดส่งแล้ว', class: 'bg-purple-100 text-purple-600 border-purple-200 dark:bg-purple-900/30 dark:border-purple-800', icon: 'local_shipping'},
        'completed': {text: 'สำเร็จแล้ว', class: 'bg-green-100 text-green-600 border-green-200 dark:bg-green-900/30 dark:border-green-800', icon: 'check_circle'},
        'cancelled': {text: 'ยกเลิก', class: 'bg-red-100 text-red-600 border-red-200 dark:bg-red-900/30 dark:border-red-800', icon: 'cancel'}
    };

    function openOrderModal(jsonData) {
        const order = JSON.parse(jsonData);
        
        document.getElementById('md_order_no').innerText = '#' + order.order_no;
        
        const dateObj = new Date(order.created_at);
        const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        document.getElementById('md_date').innerText = dateObj.toLocaleDateString('th-TH', options) + ' น.';
        
        document.getElementById('md_form_order_id').value = order.order_id;
        document.getElementById('md_status_select').value = order.status;

        // 🟢 อัปเดต Badge สถานะใน Pop-up
        const b = badgesData[order.status] || {text: 'ไม่ทราบ', class: 'bg-gray-100 text-gray-600 border-gray-200', icon: 'help'};
        document.getElementById('md_status_badge').className = `px-3 py-1 rounded-full text-xs font-bold border shadow-sm flex items-center gap-1 w-fit ${b.class}`;
        document.getElementById('md_status_badge').innerHTML = `<span class="material-icons-round text-[14px]">${b.icon}</span> ${b.text}`;

        // ข้อมูลลูกค้า
        document.getElementById('md_cus_name').innerText = order.u_name || order.u_username || 'ไม่ระบุ';
        document.getElementById('md_cus_username').innerText = '@' + (order.u_username || '');
        document.getElementById('md_cus_phone').innerText = order.u_phone || '-';
        document.getElementById('md_cus_address').innerText = order.u_address || 'ไม่มีข้อมูลที่อยู่';
        
        // 🟢 รูปโปรไฟล์ลูกค้า 🟢
        let imgUrl = order.u_image ? '../profile/uploads/' + order.u_image : "https://ui-avatars.com/api/?name=" + encodeURIComponent(order.u_name || order.u_username || 'U') + "&background=fce7f3&color=ec2d88";
        document.getElementById('md_cus_image').src = imgUrl;

        // ช่องทางชำระเงิน
        let payEl = document.getElementById('md_pay_method');
        if(order.payment_method === 'promptpay' || order.payment_method === 'โอนเงิน') {
            payEl.innerHTML = `<div class="flex items-center gap-2"><span class="material-icons-round">account_balance</span> โอนเงิน</div>`;
            payEl.className = 'font-bold text-base px-4 py-2 rounded-xl inline-block border shadow-sm text-blue-600 bg-blue-50 border-blue-200 dark:bg-blue-900/30 dark:border-blue-800';
        }
        else if(order.payment_method === 'credit_card') {
            payEl.innerHTML = `<div class="flex items-center gap-2"><span class="material-icons-round">credit_card</span> บัตรเครดิต</div>`;
            payEl.className = 'font-bold text-base px-4 py-2 rounded-xl inline-block border shadow-sm text-purple-600 bg-purple-50 border-purple-200 dark:bg-purple-900/30 dark:border-purple-800';
        }
        else if(order.payment_method === 'cod') {
            payEl.innerHTML = `<div class="flex items-center gap-2"><span class="material-icons-round">local_shipping</span> COD ปลายทาง</div>`;
            payEl.className = 'font-bold text-base px-4 py-2 rounded-xl inline-block border shadow-sm text-orange-600 bg-orange-50 border-orange-200 dark:bg-orange-900/30 dark:border-orange-800';
        } else {
            payEl.innerHTML = `<div class="flex items-center gap-2"><span class="material-icons-round">payments</span> ไม่ระบุ</div>`;
            payEl.className = 'font-bold text-base px-4 py-2 rounded-xl inline-block border shadow-sm text-gray-600 bg-gray-50 border-gray-200 dark:text-gray-300 dark:bg-gray-800 dark:border-gray-700';
        }
        
        // รูปแบบการจัดส่ง
        let shipEl = document.getElementById('md_shipping_method');
        if(order.shipping_method === 'express') {
            shipEl.innerText = 'ส่งด่วน (Express)';
            shipEl.className = 'font-bold text-base px-4 py-2 rounded-xl inline-block border shadow-sm text-purple-600 bg-purple-50 border-purple-200 dark:bg-purple-900/30 dark:border-purple-800';
        } else {
            shipEl.innerText = 'ส่งธรรมดา (Standard)';
            shipEl.className = 'font-bold text-base px-4 py-2 rounded-xl inline-block border shadow-sm text-pink-600 bg-pink-50 border-pink-200 dark:bg-pink-900/30 dark:border-pink-800';
        }

        // รูปสลิป
        const slipImg = document.getElementById('md_slip_img');
        const slipNone = document.getElementById('md_slip_none');
        if (order.slip_image) {
            slipImg.src = '../uploads/slips/' + order.slip_image;
            slipImg.classList.remove('hidden');
            slipNone.classList.add('hidden');
        } else {
            slipImg.src = '';
            slipImg.classList.add('hidden');
            slipNone.classList.remove('hidden');
        }

        // รายการสินค้า
        const itemsContainer = document.getElementById('md_items_container');
        itemsContainer.innerHTML = '';
        let itemsTotal = 0;
        
        if(order.items && order.items.length > 0) {
            order.items.forEach(item => {
                const itemPrice = item.price ? item.price : item.p_price; 
                itemsTotal += parseFloat(itemPrice) * parseInt(item.quantity);
                const imgSrc = item.p_image ? '../uploads/products/' + item.p_image : 'https://via.placeholder.com/80';
                const colorHtml = item.selected_color ? `<span class="inline-block bg-pink-50 dark:bg-gray-700 text-primary dark:text-pink-400 text-[11px] font-bold px-2 py-0.5 rounded-md border border-pink-100 dark:border-gray-600 mt-1">สี: ${item.selected_color}</span>` : '';

                itemsContainer.innerHTML += `
                    <div class="flex gap-4 items-start border-b border-gray-100 dark:border-gray-700 pb-4 last:border-0 last:pb-0">
                        <div class="w-20 h-20 bg-white dark:bg-gray-800 rounded-[1rem] p-1 border border-gray-100 dark:border-gray-600 shadow-sm flex-shrink-0">
                            <img src="${imgSrc}" class="w-full h-full object-cover rounded-xl">
                        </div>
                        <div class="flex-1">
                            <p class="font-extrabold text-gray-800 dark:text-white text-base leading-snug line-clamp-2">${item.p_name}</p>
                            ${colorHtml}
                            <div class="flex justify-between items-end mt-2">
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold text-gray-500 dark:text-gray-400">฿${parseFloat(itemPrice).toLocaleString('th-TH')} <span class="mx-1 text-xs">x</span> ${item.quantity}</span>
                                </div>
                                <span class="font-black text-primary text-lg">฿${(itemPrice * item.quantity).toLocaleString('th-TH')}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
        } else {
            itemsContainer.innerHTML = '<p class="text-center text-gray-400 text-base py-6 font-medium">ไม่พบข้อมูลสินค้า</p>';
        }

        // สรุปยอด
        const shippingCost = parseFloat(order.total_amount) - itemsTotal; 
        document.getElementById('md_sum_items').innerText = '฿' + itemsTotal.toLocaleString('th-TH', {minimumFractionDigits: 2});
        document.getElementById('md_sum_shipping').innerText = '฿' + (shippingCost > 0 ? shippingCost.toLocaleString('th-TH', {minimumFractionDigits: 2}) : '0.00');
        document.getElementById('md_sum_total').innerText = '฿' + parseFloat(order.total_amount).toLocaleString('th-TH', {minimumFractionDigits: 2});

        const modal = document.getElementById('orderModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('.modal-content').classList.remove('scale-95');
        }, 10);
    }

    function closeOrderModal() {
        const modal = document.getElementById('orderModal');
        modal.classList.add('opacity-0');
        modal.querySelector('.modal-content').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'ยืนยันการลบออเดอร์?',
            text: "ข้อมูลรายการสั่งซื้อจะถูกลบถาวร!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#9CA3AF',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-3xl dark:bg-gray-800 dark:text-white', confirmButton: 'rounded-full px-6 font-bold', cancelButton: 'rounded-full px-6 font-bold' }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?delete_id=' + id;
            }
        });
    }

    <?php if (isset($_SESSION['success_msg'])): ?>
        Swal.fire({ 
            toast: true, position: 'top-end', icon: 'success', 
            title: '<?= $_SESSION['success_msg'] ?>', 
            showConfirmButton: false, timer: 3000,
            customClass: { popup: 'rounded-2xl dark:bg-gray-800 dark:text-white' }
        });
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
</script>
</body>
</html>