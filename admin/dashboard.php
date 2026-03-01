<?php
session_start();
require_once '../config/connectdbuser.php'; 

// if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

$today = date('Y-m-d');

// ==========================================
// 1. ดึงข้อมูลสถิติภาพรวม (Stats)
// ==========================================
$sqlSales = "SELECT SUM(total_amount) as today_sales FROM `orders` WHERE DATE(created_at) = '$today' AND status IN ('processing', 'shipped', 'completed')";
$resSales = mysqli_query($conn, $sqlSales);
$todaySales = mysqli_fetch_assoc($resSales)['today_sales'] ?? 0;

$sqlNewOrders = "SELECT COUNT(order_id) as new_orders FROM `orders` WHERE DATE(created_at) = '$today'";
$resNewOrders = mysqli_query($conn, $sqlNewOrders);
$newOrders = mysqli_fetch_assoc($resNewOrders)['new_orders'] ?? 0;

$lowStockLimit = 10;
$sqlLowStock = "SELECT COUNT(p_id) as low_stock FROM `product` WHERE p_stock <= $lowStockLimit";
$resLowStock = mysqli_query($conn, $sqlLowStock);
$lowStock = mysqli_fetch_assoc($resLowStock)['low_stock'] ?? 0;

$sqlUsers = "SELECT COUNT(u_id) as total_users FROM `account`";
$resUsers = mysqli_query($conn, $sqlUsers);
$totalUsers = mysqli_fetch_assoc($resUsers)['total_users'] ?? 0;

// 🟢 แก้ที่ 6: ดึงจำนวนคำร้องเรียนใหม่ (สมมติว่าดูจากข้อความใหม่ของวันนี้)
$sqlComplaints = "SELECT COUNT(id) as new_complaints FROM `contact_messages` WHERE DATE(created_at) = '$today'";
$resComplaints = mysqli_query($conn, $sqlComplaints);
$newComplaints = mysqli_fetch_assoc($resComplaints)['new_complaints'] ?? 0;

// ==========================================
// 2. ดึงข้อมูลรายการสั่งซื้อล่าสุด 5 รายการ
// ==========================================
$recentOrders = [];
// 🟢 แก้ที่ 2: ดึง u.u_image ของ User มาด้วย (Join ตาราง user)
$sqlOrders = "
    SELECT o.order_id, o.order_no, o.total_amount, o.status, o.created_at, a.u_username, u.u_image 
    FROM `orders` o 
    LEFT JOIN `account` a ON o.u_id = a.u_id 
    LEFT JOIN `user` u ON o.u_id = u.u_id
    ORDER BY o.created_at DESC 
    LIMIT 5
";
if ($resOrders = mysqli_query($conn, $sqlOrders)) {
    while ($row = mysqli_fetch_assoc($resOrders)) {
        $o_id = $row['order_id'];
        $sqlItem = "SELECT p_name FROM `order_items` WHERE order_id = $o_id LIMIT 1";
        $resItem = mysqli_query($conn, $sqlItem);
        $itemName = mysqli_fetch_assoc($resItem)['p_name'] ?? 'ไม่มีข้อมูลสินค้า';

        // 🟢 แก้ที่ 2: นับจำนวนชิ้นสินค้ารวมทั้งหมด (SUM quantity)
        $sqlSumQty = "SELECT SUM(quantity) as total_qty FROM `order_items` WHERE order_id = $o_id";
        $resSum = mysqli_query($conn, $sqlSumQty);
        $totalQty = mysqli_fetch_assoc($resSum)['total_qty'] ?? 0;
        
        $sqlCountType = "SELECT COUNT(*) as c FROM `order_items` WHERE order_id = $o_id";
        $itemCount = mysqli_fetch_assoc(mysqli_query($conn, $sqlCountType))['c'] ?? 0;
        
        if ($itemCount > 1) {
            $itemName .= " และอื่นๆอีก " . ($itemCount - 1) . " รายการ";
        }
        
        $row['product_summary'] = $itemName;
        $row['total_items_qty'] = $totalQty; // เก็บจำนวนชิ้นรวม
        
        // จัดการรูปโปรไฟล์
        $profileImg = "https://ui-avatars.com/api/?name=" . urlencode($row['u_username'] ?? 'User') . "&background=random&color=fff&fontFamily=Prompt";
        if (!empty($row['u_image']) && file_exists("../profile/uploads/" . $row['u_image'])) {
            $profileImg = "../profile/uploads/" . $row['u_image'];
        }
        $row['avatar'] = $profileImg;

        $recentOrders[] = $row;
    }
}

function getBadge($status) {
    $badges = [
        'pending' => ['text' => 'รอชำระเงิน', 'class' => 'bg-orange-100 text-orange-600 dark:bg-orange-900/30', 'dot' => 'bg-orange-500'],
        'processing' => ['text' => 'เตรียมจัดส่ง', 'class' => 'bg-blue-100 text-blue-600 dark:bg-blue-900/30', 'dot' => 'bg-blue-500'],
        'shipped' => ['text' => 'กำลังจัดส่ง', 'class' => 'bg-purple-100 text-purple-600 dark:bg-purple-900/30', 'dot' => 'bg-purple-500'],
        'completed' => ['text' => 'สำเร็จแล้ว', 'class' => 'bg-green-100 text-green-600 dark:bg-green-900/30', 'dot' => 'bg-green-500'],
        'cancelled' => ['text' => 'ยกเลิก', 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-800', 'dot' => 'bg-gray-500']
    ];
    return $badges[$status] ?? ['text' => 'ไม่ทราบ', 'class' => 'bg-gray-100 text-gray-600', 'dot' => 'bg-gray-500'];
}

// ==========================================
// 🟢 แก้ที่ 5: จัดเตรียมข้อมูลกราฟเบื้องต้น (7 วัน)
// ==========================================
$defaultLabels = [];
$defaultData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $displayDate = date('d M', strtotime("-$i days"));
    $sqlDaily = "SELECT SUM(total_amount) as daily_sales FROM `orders` WHERE DATE(created_at) = '$date' AND status IN ('processing', 'shipped', 'completed')";
    $resDaily = mysqli_query($conn, $sqlDaily);
    $sales = mysqli_fetch_assoc($resDaily)['daily_sales'] ?? 0;
    $defaultLabels[] = $displayDate;
    $defaultData[] = (float)$sales;
}

// 🟢 หากมีการร้องขอเปลี่ยนช่วงเวลากราฟแบบ AJAX
if (isset($_GET['chart_days'])) {
    $days = (int)$_GET['chart_days'];
    if (!in_array($days, [7, 14, 30])) $days = 7;
    $ajaxLabels = [];
    $ajaxData = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $displayDate = date('d M', strtotime("-$i days"));
        $sqlDaily = "SELECT SUM(total_amount) as daily_sales FROM `orders` WHERE DATE(created_at) = '$date' AND status IN ('processing', 'shipped', 'completed')";
        $resDaily = mysqli_query($conn, $sqlDaily);
        $sales = mysqli_fetch_assoc($resDaily)['daily_sales'] ?? 0;
        $ajaxLabels[] = $displayDate;
        $ajaxData[] = (float)$sales;
    }
    header('Content-Type: application/json');
    echo json_encode(['labels' => $ajaxLabels, 'data' => $ajaxData]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Lumina Beauty - Admin Dashboard</title>

<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    tailwind.config = {
        darkMode: "class", // 🟢 แก้ที่ 3: เปิดใช้งาน Dark Mode แบบระบุ Class
        theme: {
            extend: {
                colors: {
                    primary: "#ec2d88", 
                    "primary-light": "#fce7f3",
                    secondary: "#a855f7",
                    "background-light": "#fff5f9", 
                    "background-dark": "#1F1B24", // 🟢 สีพื้นหลังโหมดมืด
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
    
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-thumb { background: #FBCFE8; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #ec2d88; }
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
                <a class="nav-item-active flex items-center gap-4 px-5 py-3.5 rounded-2xl transition-all duration-300 group" href="dashboard.php">
                    <span class="material-icons-round">dashboard</span>
                    <span class="font-bold text-[15px]">ภาพรวมระบบ</span>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted dark:text-gray-400 transition-all duration-300 group hover:pl-6" href="manage_products.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">inventory_2</span>
                    <span class="font-medium text-[15px]">จัดการสินค้า</span>
                </a>
                <a class="nav-item flex items-center justify-between px-5 py-3.5 rounded-2xl text-text-muted dark:text-gray-400 transition-all duration-300 group hover:pl-6" href="manage_orders.php">
                    <div class="flex items-center gap-4">
                        <span class="material-icons-round group-hover:scale-110 transition-transform">receipt_long</span>
                        <span class="font-medium text-[15px]">รายการสั่งซื้อ</span>
                    </div>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted dark:text-gray-400 transition-all duration-300 group hover:pl-6" href="manage_customers.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">group</span>
                    <span class="font-medium text-[15px]">ข้อมูลลูกค้า</span>
                </a>
                <a class="nav-item flex items-center justify-between px-5 py-3.5 rounded-2xl text-text-muted dark:text-gray-400 transition-all duration-300 group hover:pl-6" href="manage_complaints.php">
                    <div class="flex items-center gap-4">
                        <span class="material-icons-round group-hover:scale-110 transition-transform">forum</span>
                        <span class="font-medium text-[15px]">คำร้องเรียน</span>
                    </div>
                    <?php if($newComplaints > 0): ?>
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
                <p class="text-sm font-bold text-primary dark:text-pink-400">ต้องการความช่วยเหลือ</p>
                <p class="text-xs text-text-muted dark:text-gray-400">ติดต่อทีมพัฒนาระบบ</p>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0">
        <header class="flex items-center justify-between px-6 py-4 lg:px-10 lg:py-5 glass-panel sticky top-0 z-50 transition-colors duration-300">
            <div class="flex items-center gap-4 lg:hidden">
                <button class="p-2 text-text-main dark:text-white hover:bg-pink-50 dark:hover:bg-gray-800 rounded-xl transition-colors">
                    <span class="material-icons-round">menu</span>
                </button>
                <span class="font-bold text-xl text-primary flex items-center gap-1"><span class="material-icons-round">spa</span> Lumina</span>
            </div>
            
            <div class="hidden md:flex flex-1"></div>
            
            <div class="flex items-center gap-4 lg:gap-6 ml-auto">
                <button class="w-10 h-10 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-primary dark:hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-800 rounded-full transition-all" onclick="toggleTheme()">
                    <span class="material-icons-round dark:hidden text-2xl">dark_mode</span>
                    <span class="material-icons-round hidden dark:block text-yellow-400 text-2xl">light_mode</span>
                </button>
                
                <div class="relative group flex items-center">
                    <?php 
                        $adminName = $_SESSION['admin_username'] ?? 'Admin'; 
                        $adminAvatar = "admin.jpg";
                    ?>
                    <a href="#" class="block w-11 h-11 rounded-full bg-gradient-to-tr from-purple-400 to-indigo-400 p-[2px] shadow-sm hover:shadow-glow hover:scale-105 transition-all cursor-pointer">
                        <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-full h-full overflow-hidden">
                            <img alt="Admin Profile" class="w-full h-full rounded-full object-cover" src="<?= $adminAvatar ?>"/>
                        </div>
                    </a>
                    
                    <div class="absolute right-0 hidden pt-4 top-full w-[300px] z-50 group-hover:block cursor-default">
                        <div class="bg-white dark:bg-surface-dark rounded-3xl shadow-soft border border-pink-100 dark:border-gray-700 overflow-hidden p-5 relative">
                            
                            <div class="text-center mb-4">
                                <span class="text-sm font-bold text-purple-500 bg-purple-50 dark:bg-purple-900/30 px-3 py-1 rounded-full border border-purple-100 dark:border-purple-800/50">
                                    Administrator Mode
                                </span>
                            </div>

                            <div class="flex justify-center relative mb-3">
                                <div class="rounded-full p-[3px] bg-purple-500 shadow-md">
                                    <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-16 h-16 overflow-hidden">
                                        <img src="<?= $adminAvatar ?>" alt="Profile" class="w-full h-full rounded-full object-cover">
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mb-6">
                                <h3 class="text-[20px] font-bold text-gray-800 dark:text-white">สวัสดี, คุณ <?= htmlspecialchars($adminName) ?></h3>
                                <p class="text-xs text-gray-500 mt-1">ผู้จัดการระบบสูงสุด</p>
                            </div>

                            <div class="flex flex-col gap-3">
                                <a href="#" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-purple-500 hover:bg-purple-500 hover:text-white rounded-full py-2.5 transition text-[14px] font-semibold text-purple-500">
                                    <span class="material-icons-round text-[18px]">manage_accounts</span> จัดการบัญชี
                                </a>
                                <a href="../auth/logout.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-red-500 hover:bg-red-500 hover:text-white rounded-full py-2.5 transition text-[14px] font-semibold text-red-500">
                                    <span class="material-icons-round text-[18px]">logout</span> ออกจากระบบ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="p-6 lg:p-8 flex flex-col gap-8 max-w-[1600px] mx-auto w-full">
            
            <div class="relative w-full rounded-3xl overflow-hidden bg-gradient-to-r from-pink-100 via-purple-50 to-blue-50 dark:from-gray-800 dark:via-gray-800 dark:to-gray-900 shadow-soft p-8 lg:p-10 flex flex-col items-start gap-4 border border-white dark:border-gray-700">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/80 dark:bg-black/30 backdrop-blur-sm w-fit shadow-sm border border-white dark:border-gray-600 z-10">
                    <span class="w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse"></span>
                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300">ระบบทำงานปกติ (อัปเดตล่าสุด: <?= date('H:i') ?> น.)</span>
                </div>
                
                <div class="z-10 flex flex-col gap-2 mt-2">
                    <h1 class="text-4xl md:text-5xl font-black text-gray-800 dark:text-white tracking-tight leading-tight">
                        ภาพรวมธุรกิจวันนี้ 
                    </h1>
                    <h1 class="text-4xl md:text-5xl font-black tracking-tight leading-tight">
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-purple-500">เติบโตอย่างต่อเนื่อง 🚀</span>
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 font-medium text-base mt-2">จัดการออเดอร์ ดูยอดขาย เช็คสต๊อกสินค้า และจำนวนสมาชิก</p>
                </div>
                
                <div class="absolute right-10 top-1/2 -translate-y-1/2 w-48 h-48 bg-white/40 dark:bg-white/5 rounded-full blur-2xl"></div>
                <div class="absolute -right-10 -bottom-20 w-80 h-80 bg-purple-200/40 dark:bg-purple-500/10 rounded-full blur-3xl"></div>
                <div class="absolute -left-10 -top-20 w-80 h-80 bg-pink-200/40 dark:bg-pink-500/10 rounded-full blur-3xl"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
                <div class="bg-surface-white dark:bg-surface-dark p-6 rounded-3xl shadow-card hover:shadow-soft transition-all duration-300 border border-gray-100 dark:border-gray-700 group relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-50 dark:bg-pink-900/20 rounded-full group-hover:scale-150 transition-transform duration-500 ease-out z-0"></div>
                    <div class="relative z-10 flex justify-between items-start mb-4">
                        <div class="w-14 h-14 rounded-2xl bg-pink-100 dark:bg-gray-800 border dark:border-gray-700 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-colors">
                            <span class="material-icons-round text-3xl">payments</span>
                        </div>
                    </div>
                    <p class="relative z-10 text-gray-500 dark:text-gray-400 text-sm font-semibold mb-1">ยอดขายวันนี้</p>
                    <h3 class="relative z-10 text-3xl font-extrabold text-gray-800 dark:text-white tracking-tight">฿<?= number_format($todaySales) ?></h3>
                </div>
                <div class="bg-surface-white dark:bg-surface-dark p-6 rounded-3xl shadow-card hover:shadow-soft transition-all duration-300 border border-gray-100 dark:border-gray-700 group relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-50 dark:bg-blue-900/20 rounded-full group-hover:scale-150 transition-transform duration-500 ease-out z-0"></div>
                    <div class="relative z-10 flex justify-between items-start mb-4">
                        <div class="w-14 h-14 rounded-2xl bg-blue-100 dark:bg-gray-800 border dark:border-gray-700 flex items-center justify-center text-blue-600 group-hover:bg-blue-500 group-hover:text-white transition-colors">
                            <span class="material-icons-round text-3xl">local_mall</span>
                        </div>
                    </div>
                    <p class="relative z-10 text-gray-500 dark:text-gray-400 text-sm font-semibold mb-1">คำสั่งซื้อใหม่ (วันนี้)</p>
                    <h3 class="relative z-10 text-3xl font-extrabold text-gray-800 dark:text-white tracking-tight"><?= number_format($newOrders) ?> <span class="text-sm text-gray-400 font-medium">รายการ</span></h3>
                </div>
                <div class="bg-surface-white dark:bg-surface-dark p-6 rounded-3xl shadow-card hover:shadow-soft transition-all duration-300 border border-gray-100 dark:border-gray-700 group relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-orange-50 dark:bg-orange-900/20 rounded-full group-hover:scale-150 transition-transform duration-500 ease-out z-0"></div>
                    <div class="relative z-10 flex justify-between items-start mb-4">
                        <div class="w-14 h-14 rounded-2xl bg-orange-100 dark:bg-gray-800 border dark:border-gray-700 flex items-center justify-center text-orange-500 group-hover:bg-orange-500 group-hover:text-white transition-colors">
                            <span class="material-icons-round text-3xl">inventory</span>
                        </div>
                    </div>
                    <p class="relative z-10 text-gray-500 dark:text-gray-400 text-sm font-semibold mb-1">สินค้าใกล้หมดสต๊อก</p>
                    <h3 class="relative z-10 text-3xl font-extrabold text-gray-800 dark:text-white tracking-tight"><?= number_format($lowStock) ?> <span class="text-sm text-gray-400 font-medium">รายการ</span></h3>
                </div>
                <div class="bg-surface-white dark:bg-surface-dark p-6 rounded-3xl shadow-card hover:shadow-soft transition-all duration-300 border border-gray-100 dark:border-gray-700 group relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-50 dark:bg-purple-900/20 rounded-full group-hover:scale-150 transition-transform duration-500 ease-out z-0"></div>
                    <div class="relative z-10 flex justify-between items-start mb-4">
                        <div class="w-14 h-14 rounded-2xl bg-purple-100 dark:bg-gray-800 border dark:border-gray-700 flex items-center justify-center text-purple-600 group-hover:bg-purple-500 group-hover:text-white transition-colors">
                            <span class="material-icons-round text-3xl">group</span>
                        </div>
                    </div>
                    <p class="relative z-10 text-gray-500 dark:text-gray-400 text-sm font-semibold mb-1">สมาชิกรวมทั้งหมด</p>
                    <h3 class="relative z-10 text-3xl font-extrabold text-gray-800 dark:text-white tracking-tight"><?= number_format($totalUsers) ?> <span class="text-sm text-gray-400 font-medium">คน</span></h3>
                </div>
            </div>

            <div class="bg-surface-white dark:bg-surface-dark rounded-3xl p-6 lg:p-8 shadow-card border border-gray-100 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2"><span class="material-icons-round text-primary">trending_up</span> แนวโน้มยอดขายย้อนหลัง</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">สรุปข้อมูลยอดขาย</p>
                    </div>
                    
                    <select id="chartRangeSelect" onchange="updateChartData()" class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-bold rounded-xl focus:ring-primary focus:border-primary px-4 py-2 outline-none cursor-pointer">
                        <option value="7">7 วันล่าสุด</option>
                        <option value="14">14 วันล่าสุด</option>
                        <option value="30">30 วันล่าสุด</option>
                    </select>
                </div>
                <div class="relative h-[320px] w-full">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="bg-surface-white dark:bg-surface-dark rounded-3xl shadow-card overflow-hidden border border-gray-100 dark:border-gray-700 mb-10">
                <div class="p-6 lg:px-8 lg:py-6 flex items-center justify-between border-b border-gray-50 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/50">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2"><span class="material-icons-round text-blue-500">receipt_long</span> ออเดอร์ล่าสุด</h3>
                    <a class="text-primary text-sm font-bold hover:underline bg-pink-50 dark:bg-gray-700 px-4 py-2 rounded-full transition-colors" href="manage_orders.php">ดูทั้งหมด</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[800px]">
                        <thead>
                            <tr class="bg-white dark:bg-surface-dark text-gray-400 dark:text-gray-500 text-[13px] uppercase tracking-wider border-b border-gray-100 dark:border-gray-700">
                                <th class="px-6 py-4 font-bold">หมายเลขคำสั่งซื้อ</th>
                                <th class="px-6 py-4 font-bold">ลูกค้า</th>
                                <th class="px-6 py-4 font-bold">สินค้า</th>
                                <th class="px-6 py-4 font-bold text-center">จำนวนชิ้น</th> <th class="px-6 py-4 font-bold">วันที่สั่งซื้อ</th>
                                <th class="px-6 py-4 font-bold">สถานะ</th>
                                <th class="px-6 py-4 font-bold text-right">ยอดรวม</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-400 dark:text-gray-500">
                                        <span class="material-icons-round text-4xl mb-2 opacity-50">inbox</span>
                                        <p>ยังไม่มีรายการสั่งซื้อล่าสุด</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): 
                                    $badge = getBadge($order['status']);
                                ?>
                                <tr class="hover:bg-pink-50/30 dark:hover:bg-gray-800/50 transition-colors border-b border-gray-50 dark:border-gray-700/50 last:border-0 group cursor-pointer" onclick="window.location='manage_order_detail.php?id=<?= $order['order_id'] ?>'">
                                    <td class="px-6 py-4 font-bold text-primary group-hover:text-pink-600 transition-colors">#<?= htmlspecialchars($order['order_no']) ?></td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <img src="<?= htmlspecialchars($order['avatar']) ?>" class="w-9 h-9 rounded-full object-cover shadow-sm border border-gray-100 dark:border-gray-600">
                                            <span class="font-bold text-gray-700 dark:text-gray-200"><?= htmlspecialchars($order['u_username'] ?? 'ลูกค้าทั่วไป') ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400 font-medium truncate max-w-[200px]" title="<?= htmlspecialchars($order['product_summary']) ?>">
                                        <?= htmlspecialchars($order['product_summary']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center font-bold text-gray-700 dark:text-gray-300">
                                        <?= $order['total_items_qty'] ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400 font-medium">
                                        <?= date('d M Y, H:i', strtotime($order['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold <?= $badge['class'] ?> shadow-sm">
                                            <span class="w-1.5 h-1.5 rounded-full <?= $badge['dot'] ?> animate-pulse"></span> <?= $badge['text'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-gray-800 dark:text-white text-right text-base">
                                        ฿<?= number_format($order['total_amount']) ?>
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

<script>
    // 🟢 ระบบ Dark Mode ของ Admin
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }
    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        if (htmlEl.classList.contains('dark')) {
            localStorage.setItem('theme', 'dark');
            if(salesChart) updateChartColors(true);
        } else {
            localStorage.setItem('theme', 'light');
            if(salesChart) updateChartColors(false);
        }
    }

    // 🟢 กราฟ Chart.js
    let salesChart;
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    // ข้อมูลตั้งต้น 7 วัน (ดึงจาก PHP ด้านบน)
    const initialLabels = <?= json_encode($defaultLabels) ?>;
    const initialData = <?= json_encode($defaultData) ?>;

    let gradient = ctx.createLinearGradient(0, 0, 0, 320);
    gradient.addColorStop(0, 'rgba(236, 45, 136, 0.5)'); 
    gradient.addColorStop(1, 'rgba(236, 45, 136, 0.0)'); 

    function initChart(labels, dataValues) {
        const isDark = document.documentElement.classList.contains('dark');
        const gridColor = isDark ? '#374151' : '#f3f4f6';
        const textColor = isDark ? '#9ca3af' : '#9ca3af';

        salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ยอดขาย (บาท)',
                    data: dataValues,
                    borderColor: '#ec2d88',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#ec2d88',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#ec2d88',
                    pointHoverBorderColor: '#ffffff',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(31, 41, 55, 0.9)',
                        titleFont: { family: 'Prompt', size: 13 },
                        bodyFont: { family: 'Prompt', size: 15, weight: 'bold' },
                        padding: 12, cornerRadius: 12, displayColors: false,
                        callbacks: {
                            label: function(context) { return 'ยอดขาย: ฿' + context.parsed.y.toLocaleString(); }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor, drawBorder: false, borderDash: [5, 5] },
                        ticks: { font: { family: 'Prompt', size: 12 }, color: textColor, callback: function(value) { return '฿' + value.toLocaleString(); }, padding: 10 },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Prompt', size: 12 }, color: textColor, padding: 10 },
                        border: { display: false }
                    }
                },
                interaction: { intersect: false, mode: 'index' },
            }
        });
    }

    // ฟังก์ชันเปลี่ยนสีเส้นตารางเวลากดเปลี่ยนธีม
    function updateChartColors(isDark) {
        if(!salesChart) return;
        const gridColor = isDark ? '#374151' : '#f3f4f6';
        salesChart.options.scales.y.grid.color = gridColor;
        salesChart.update();
    }

    // 🟢 ฟังก์ชันอัปเดตข้อมูลกราฟด้วย AJAX (เมื่อเปลี่ยน Dropdown)
    function updateChartData() {
        const days = document.getElementById('chartRangeSelect').value;
        fetch('dashboard.php?chart_days=' + days)
            .then(response => response.json())
            .then(data => {
                salesChart.data.labels = data.labels;
                salesChart.data.datasets[0].data = data.data;
                salesChart.update();
            });
    }

    // รันกราฟครั้งแรกเมื่อโหลดหน้า
    document.addEventListener("DOMContentLoaded", function() {
        initChart(initialLabels, initialData);
    });
</script>
</body>
</html>