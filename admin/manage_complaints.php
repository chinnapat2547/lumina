<?php
session_start();
require_once '../config/connectdbuser.php';

$adminName = $_SESSION['admin_username'] ?? 'Admin Nina'; 
$adminAvatar = "https://ui-avatars.com/api/?name=" . urlencode($adminName) . "&background=a855f7&color=fff";

// ==========================================
// ส่วนดึงข้อมูลแจ้งเตือนสำหรับ Sidebar (ต้องมีทุกหน้า)
// ==========================================
$countPending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders` WHERE status='pending'"))['c'] ?? 0;
$newComplaints = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `contact_messages` WHERE status='pending' OR status IS NULL"))['c'] ?? 0;

// ==========================================
// 1. จัดการลบคำร้องเรียน (GET)
// ==========================================
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    mysqli_query($conn, "DELETE FROM `contact_messages` WHERE id = $del_id");
    $_SESSION['success_msg'] = "ลบข้อความคำร้องเรียนเรียบร้อยแล้ว!";
    header("Location: manage_complaints.php");
    exit();
}

// ==========================================
// 2. จัดการอัปเดตสถานะคำร้องเรียน (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $msg_id = (int)$_POST['msg_id'];
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $sqlUp = "UPDATE `contact_messages` SET status = '$new_status' WHERE id = $msg_id";
    if (mysqli_query($conn, $sqlUp)) {
        $_SESSION['success_msg'] = "อัปเดตสถานะเรียบร้อยแล้ว!";
    }
    header("Location: manage_complaints.php");
    exit();
}

// ==========================================
// 3. จัดการระบบค้นหา และ ตัวกรองสถานะ (GET)
// ==========================================
$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? 'all';

$whereClauseArr = [];

if ($filter_status !== 'all') {
    $whereClauseArr[] = "c.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}

if ($search !== '') {
    $searchEsc = mysqli_real_escape_string($conn, $search);
    $whereClauseArr[] = "(c.name LIKE '%$searchEsc%' OR c.email LIKE '%$searchEsc%' OR c.subject LIKE '%$searchEsc%' OR c.message LIKE '%$searchEsc%')";
}

$whereClause = count($whereClauseArr) > 0 ? "WHERE " . implode(" AND ", $whereClauseArr) : "";

// ==========================================
// 4. ดึงข้อมูลคำร้องเรียน (เชื่อมตาราง account & user ผ่าน email เพื่อดึงรูปโปรไฟล์)
// ==========================================
$complaints = [];
// ใช้ LEFT JOIN ผ่านอีเมล เพื่อดึงรูปของ User ที่เป็นสมาชิก ถ้าไม่ใช่สมาชิกก็ให้เป็นค่าว่าง
$sqlComplaints = "
    SELECT c.*, a.u_username, u.u_image 
    FROM `contact_messages` c
    LEFT JOIN `account` a ON c.email = a.u_email
    LEFT JOIN `user` u ON a.u_id = u.u_id
    $whereClause
    ORDER BY c.created_at DESC
";

if ($res = mysqli_query($conn, $sqlComplaints)) {
    while ($row = mysqli_fetch_assoc($res)) {
        // จัดการรูปโปรไฟล์
        if (!empty($row['u_image']) && file_exists("../profile/uploads/" . $row['u_image'])) {
            $row['display_image'] = "../profile/uploads/" . $row['u_image'];
            $row['is_member'] = true;
        } else {
            $row['display_image'] = "https://ui-avatars.com/api/?name=" . urlencode($row['name']) . "&background=fce7f3&color=ec2d88";
            $row['is_member'] = !empty($row['u_username']);
        }
        
        // กำหนดค่าเริ่มต้นของสถานะหากไม่มี (เผื่อตารางเดิมไม่มี)
        if (!isset($row['status']) || empty($row['status'])) {
            $row['status'] = 'pending';
        }
        
        $complaints[] = $row;
    }
}

// ==========================================
// 5. สถิติสำหรับแสดงด้านบน
// ==========================================
$stat_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `contact_messages`"))['c'] ?? 0;
$stat_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `contact_messages` WHERE status='pending' OR status IS NULL"))['c'] ?? 0;
$stat_processing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `contact_messages` WHERE status='processing'"))['c'] ?? 0;
$stat_resolved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `contact_messages` WHERE status='resolved'"))['c'] ?? 0;

$newComplaints = $stat_pending; // ใช้สำหรับแสดงแจ้งเตือนที่ Sidebar

// ฟังก์ชันทำป้ายกำกับสถานะ
function getComplaintBadge($status) {
    $badges = [
        'pending' => ['text' => 'รอตรวจสอบ', 'class' => 'bg-red-100 text-red-600 border-red-200 dark:bg-red-900/30 dark:border-red-800', 'icon' => 'mark_email_unread'],
        'processing' => ['text' => 'กำลังดำเนินการ', 'class' => 'bg-orange-100 text-orange-600 border-orange-200 dark:bg-orange-900/30 dark:border-orange-800', 'icon' => 'hourglass_top'],
        'resolved' => ['text' => 'แก้ไขแล้ว', 'class' => 'bg-green-100 text-green-600 border-green-200 dark:bg-green-900/30 dark:border-green-800', 'icon' => 'check_circle']
    ];
    return $badges[$status] ?? ['text' => 'รอตรวจสอบ', 'class' => 'bg-red-100 text-red-600 border-red-200 dark:bg-red-900/30 dark:border-red-800', 'icon' => 'mark_email_unread'];
}


?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>จัดการคำร้องเรียน - Lumina Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    tailwind.config = {
        darkMode: "class",
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
    .custom-scroll::-webkit-scrollbar { width: 6px; } 
    .custom-scroll::-webkit-scrollbar-thumb { background: #FBCFE8; border-radius: 10px; } 
    .custom-scroll::-webkit-scrollbar-thumb:hover { background: #ec2d88; }
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
                <a class="nav-item flex items-center justify-between px-5 py-3.5 rounded-2xl text-text-muted dark:text-gray-400 transition-all duration-300 group hover:pl-6" href="manage_orders.php">
                    <div class="flex items-center gap-4">
                        <span class="material-icons-round group-hover:scale-110 transition-transform">receipt_long</span>
                        <span class="font-medium text-[15px]">รายการสั่งซื้อ</span>
                    </div>
                    <?php if($newOrders > 0): ?>
                        <span class="bg-primary text-white text-[11px] font-bold px-2 py-0.5 rounded-full shadow-sm"><?= $newOrders ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted dark:text-gray-400 transition-all duration-300 group hover:pl-6" href="manage_customers.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">group</span>
                    <span class="font-medium text-[15px]">ข้อมูลลูกค้า</span>
                </a>
                <a class="nav-item-active flex items-center justify-between px-5 py-3.5 rounded-2xl transition-all duration-300 group" href="manage_complaints.php">
                    <div class="flex items-center gap-4">
                        <span class="material-icons-round">forum</span>
                        <span class="font-bold text-[15px]">คำร้องเรียน</span>
                    </div>
                    <?php if($newComplaints > 0): ?>
                        <span class="bg-white text-primary text-[11px] font-extrabold px-2 py-0.5 rounded-full shadow-sm"><?= $newComplaints ?></span>
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
            <form method="GET" action="manage_complaints.php" class="hidden md:flex flex-1 max-w-md relative group items-center">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <span class="material-icons-round text-gray-400 group-focus-within:text-primary transition-colors text-[20px]">search</span>
                </div>
                <input name="search" value="<?= htmlspecialchars($search) ?>" class="block w-full pl-12 pr-10 py-2.5 rounded-full border border-pink-100 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm text-sm placeholder-gray-400 dark:text-white focus:ring-2 focus:ring-primary/20 transition-all outline-none" placeholder="ค้นหาชื่อ, อีเมล, หัวข้อ, ข้อความ..." type="text"/>
                <?php if (!empty($search)): ?>
                    <a href="manage_complaints.php?status=<?= htmlspecialchars($filter_status) ?>" class="absolute right-3 w-6 h-6 bg-pink-50 dark:bg-gray-700 hover:bg-red-100 dark:hover:bg-red-900/30 text-primary dark:text-pink-400 hover:text-red-500 rounded-full transition-colors flex items-center justify-center shadow-sm" title="ล้างการค้นหา">
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
                            <img alt="Admin Profile" class="w-full h-full rounded-full object-cover" src="<?= $adminAvatar ?>"/>
                        </div>
                    </a>
                    
                    <div class="absolute right-0 hidden pt-4 top-full w-[300px] z-50 group-hover:block cursor-default">
                        <div class="bg-surface-white dark:bg-surface-dark rounded-3xl shadow-soft border border-pink-100 dark:border-gray-700 overflow-hidden p-5 relative">
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
                <div>
                    <h1 class="text-4xl font-extrabold text-gray-800 dark:text-white tracking-tight flex items-center gap-3">
                        คำร้องเรียนจากลูกค้า
                    </h1>
                    <span class="text-base font-bold bg-pink-100 dark:bg-pink-900/30 border border-pink-200 dark:border-pink-800/50 text-primary dark:text-pink-400 w-fit px-4 py-1.5 rounded-full shadow-sm mt-2 inline-block">
                        ทั้งหมด <?= number_format($stat_total) ?> รายการ
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 lg:gap-6 mt-2">
                <div class="bg-surface-white dark:bg-surface-dark p-6 rounded-3xl shadow-card border border-gray-100 dark:border-gray-700 flex items-center gap-4 hover:shadow-soft transition-shadow">
                    <div class="w-14 h-14 rounded-2xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center text-blue-500">
                        <span class="material-icons-round text-3xl">all_inbox</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-semibold mb-1">ข้อความทั้งหมด</p>
                        <h3 class="text-3xl font-extrabold text-gray-800 dark:text-white"><?= number_format($stat_total) ?></h3>
                    </div>
                </div>
                <div class="bg-surface-white dark:bg-surface-dark p-6 rounded-3xl shadow-card border border-gray-100 dark:border-gray-700 flex items-center gap-4 hover:shadow-soft transition-shadow relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-red-50 dark:bg-red-900/20 rounded-full z-0"></div>
                    <div class="relative z-10 w-14 h-14 rounded-2xl bg-red-100 dark:bg-gray-800 flex items-center justify-center text-red-600 dark:border-gray-700">
                        <span class="material-icons-round text-3xl">mark_email_unread</span>
                    </div>
                    <div class="relative z-10">
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-semibold mb-1">รอตรวจสอบ</p>
                        <h3 class="text-3xl font-extrabold text-red-600"><?= number_format($stat_pending) ?></h3>
                    </div>
                </div>
                <div class="bg-surface-white dark:bg-surface-dark p-6 rounded-3xl shadow-card border border-gray-100 dark:border-gray-700 flex items-center gap-4 hover:shadow-soft transition-shadow">
                    <div class="w-14 h-14 rounded-2xl bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center text-orange-500">
                        <span class="material-icons-round text-3xl">hourglass_top</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-semibold mb-1">กำลังดำเนินการ</p>
                        <h3 class="text-3xl font-extrabold text-gray-800 dark:text-white"><?= number_format($stat_processing) ?></h3>
                    </div>
                </div>
                <div class="bg-surface-white dark:bg-surface-dark p-6 rounded-3xl shadow-card border border-gray-100 dark:border-gray-700 flex items-center gap-4 hover:shadow-soft transition-shadow">
                    <div class="w-14 h-14 rounded-2xl bg-green-50 dark:bg-green-900/20 flex items-center justify-center text-green-600">
                        <span class="material-icons-round text-3xl">check_circle</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-semibold mb-1">แก้ไขแล้ว</p>
                        <h3 class="text-3xl font-extrabold text-gray-800 dark:text-white"><?= number_format($stat_resolved) ?></h3>
                    </div>
                </div>
            </div>

            <div class="bg-surface-white dark:bg-surface-dark rounded-full p-2 shadow-sm border border-gray-100 dark:border-gray-700 inline-flex overflow-x-auto custom-scroll w-full sm:w-auto mt-2">
                <a href="?status=all<?= $search !== '' ? '&search='.urlencode($search) : '' ?>" class="px-6 py-2.5 rounded-full text-[15px] font-bold whitespace-nowrap transition-all <?= $filter_status == 'all' ? 'bg-primary text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:bg-pink-50 dark:hover:bg-gray-800 hover:text-primary' ?>">
                    ทั้งหมด
                </a>
                <a href="?status=pending<?= $search !== '' ? '&search='.urlencode($search) : '' ?>" class="px-6 py-2.5 rounded-full text-[15px] font-bold whitespace-nowrap transition-all flex items-center gap-2 <?= $filter_status == 'pending' ? 'bg-primary text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:bg-pink-50 dark:hover:bg-gray-800 hover:text-primary' ?>">
                    รอตรวจสอบ <?= $stat_pending > 0 ? '<span class="w-5 h-5 flex items-center justify-center bg-red-500 text-white text-[11px] rounded-full shadow-sm">'.$stat_pending.'</span>' : '' ?>
                </a>
                <a href="?status=processing<?= $search !== '' ? '&search='.urlencode($search) : '' ?>" class="px-6 py-2.5 rounded-full text-[15px] font-bold whitespace-nowrap transition-all <?= $filter_status == 'processing' ? 'bg-primary text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:bg-pink-50 dark:hover:bg-gray-800 hover:text-primary' ?>">
                    กำลังดำเนินการ (<?= $stat_processing ?>)
                </a>
                <a href="?status=resolved<?= $search !== '' ? '&search='.urlencode($search) : '' ?>" class="px-6 py-2.5 rounded-full text-[15px] font-bold whitespace-nowrap transition-all <?= $filter_status == 'resolved' ? 'bg-primary text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:bg-pink-50 dark:hover:bg-gray-800 hover:text-primary' ?>">
                    แก้ไขแล้ว (<?= $stat_resolved ?>)
                </a>
            </div>

            <div class="bg-surface-white dark:bg-surface-dark rounded-[2.5rem] shadow-card overflow-hidden border border-gray-100 dark:border-gray-700 relative">
                <div class="overflow-x-auto min-h-[400px]">
                    <table class="w-full text-left border-collapse min-w-[1000px]">
                        <thead>
                            <tr class="bg-gray-50/80 dark:bg-gray-800/50 text-gray-600 dark:text-gray-400 text-[15px] uppercase tracking-wider border-b border-gray-100 dark:border-gray-700">
                                <th class="px-6 py-5 font-bold pl-8">ผู้ส่งคำร้อง</th>
                                <th class="px-6 py-5 font-bold w-1/3">หัวข้อ / ข้อความย่อ</th>
                                <th class="px-6 py-5 font-bold text-center">วันที่ส่ง</th>
                                <th class="px-6 py-5 font-bold text-center">สถานะ</th>
                                <th class="px-6 py-5 font-bold text-center pr-8">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-50 dark:divide-gray-700/50">
                            <?php if (empty($complaints)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-20 text-center text-gray-400 dark:text-gray-500">
                                        <div class="flex flex-col items-center justify-center">
                                            <span class="material-icons-round text-7xl text-gray-200 dark:text-gray-600 mb-4">speaker_notes_off</span>
                                            <p class="text-lg font-medium">ไม่พบข้อความคำร้องเรียน</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($complaints as $c): 
                                    $badge = getComplaintBadge($c['status']);
                                    $json = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="hover:bg-pink-50/30 dark:hover:bg-gray-800/30 transition-colors group cursor-pointer" onclick="openComplaintModal('<?= $json ?>')">
                                    <td class="px-6 py-5 pl-8">
                                        <div class="flex items-center gap-4">
                                            <div class="w-14 h-14 mx-auto rounded-[1rem] overflow-hidden border border-gray-100 dark:border-gray-700 shadow-sm bg-white dark:bg-gray-800 flex-shrink-0 p-0.5">
                                                <img src="<?= $c['display_image'] ?>" class="w-full h-full object-cover rounded-xl group-hover:scale-110 transition-transform duration-500">
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-extrabold text-gray-900 dark:text-white text-lg group-hover:text-primary transition-colors flex items-center gap-1">
                                                    <?= htmlspecialchars($c['name']) ?>
                                                    <?php if($c['is_member']): ?>
                                                        <span class="material-icons-round text-[14px] text-blue-500" title="สมาชิกระบบ">verified</span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="text-sm text-gray-500 dark:text-gray-400 mt-1"><?= htmlspecialchars($c['email']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex flex-col">
                                            <span class="text-gray-800 dark:text-gray-200 font-bold text-base line-clamp-1 mb-1"><?= htmlspecialchars($c['subject'] ?: 'ไม่มีหัวข้อ') ?></span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2 leading-relaxed"><?= htmlspecialchars($c['message']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-gray-500 dark:text-gray-400 font-medium text-base text-center whitespace-nowrap">
                                        <?= date('d M Y, H:i', strtotime($c['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <span class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-[13px] font-bold border shadow-sm <?= $badge['class'] ?>">
                                            <span class="material-icons-round text-[16px]"><?= $badge['icon'] ?></span> <?= $badge['text'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-5 pr-8 text-center" onclick="event.stopPropagation();">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick="openComplaintModal('<?= $json ?>')" class="w-11 h-11 rounded-full text-gray-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-gray-700 transition-colors flex items-center justify-center shadow-sm border border-gray-100 dark:border-gray-600" title="อ่านข้อความ">
                                                <span class="material-icons-round text-[24px]">visibility</span>
                                            </button>
                                            <button onclick="confirmDelete(<?= $c['id'] ?>)" class="w-11 h-11 rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-gray-700 transition-colors flex items-center justify-center shadow-sm border border-gray-100 dark:border-gray-600" title="ลบข้อความ">
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

<div id="complaintModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden items-center justify-center opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-surface-white dark:bg-surface-dark rounded-[2.5rem] w-full max-w-4xl h-auto max-h-[95vh] shadow-2xl flex flex-col overflow-hidden modal-content transform scale-95 transition-transform duration-300 border border-gray-100 dark:border-gray-700 relative">
        
        <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800/50">
            <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white flex items-center gap-3">
                <div class="w-12 h-12 bg-white dark:bg-gray-700 rounded-full flex items-center justify-center text-primary shadow-sm border border-gray-200 dark:border-gray-600">
                    <span class="material-icons-round text-2xl">forum</span>
                </div>
                รายละเอียดคำร้องเรียน
            </h2>
            <button type="button" onclick="closeModal('complaintModal')" class="w-12 h-12 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 shadow-sm transition-colors">
                <span class="material-icons-round text-[24px]">close</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto custom-scroll p-8 bg-white dark:bg-surface-dark space-y-6">
            
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50 dark:bg-gray-800/50 p-6 rounded-3xl border border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-4">
                    <img id="md_image" src="" class="w-16 h-16 rounded-[1rem] object-cover shadow-sm border-2 border-white dark:border-gray-600">
                    <div>
                        <p class="font-extrabold text-gray-900 dark:text-white text-xl flex items-center gap-1">
                            <span id="md_name"></span>
                            <span id="md_member_icon" class="material-icons-round text-[16px] text-blue-500 hidden" title="สมาชิกระบบ">verified</span>
                        </p>
                        <p class="text-base font-medium text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-2">
                            <span class="material-icons-round text-[16px]">email</span> <span id="md_email"></span>
                        </p>
                    </div>
                </div>
                <div class="text-left md:text-right">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">วันที่ส่งข้อความ</p>
                    <p class="font-bold text-gray-800 dark:text-white text-base" id="md_date"></p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-3xl p-8 shadow-sm border border-gray-100 dark:border-gray-700">
                <h3 class="text-xl font-extrabold text-gray-900 dark:text-white mb-4 pb-4 border-b border-gray-100 dark:border-gray-700" id="md_subject">หัวข้อข้อความ</h3>
                <div class="text-gray-700 dark:text-gray-300 text-lg leading-relaxed whitespace-pre-line min-h-[150px]" id="md_message">
                    เนื้อหาข้อความจะแสดงที่นี่...
                </div>
            </div>

        </div>

        <div class="px-8 py-6 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex flex-col sm:flex-row justify-between items-center gap-4">
            
            <form action="" method="POST" class="flex items-center gap-3 w-full sm:w-auto bg-white dark:bg-gray-800 p-2 rounded-full border border-gray-200 dark:border-gray-600 shadow-sm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="msg_id" id="md_form_msg_id" value="">
                <span class="pl-4 text-sm font-bold text-gray-500 dark:text-gray-400">เปลี่ยนสถานะ:</span>
                <select name="status" id="md_status_select" class="text-[15px] font-bold text-gray-700 dark:text-gray-200 bg-transparent border-none focus:ring-0 cursor-pointer py-1.5 outline-none">
                    <option value="pending" class="text-gray-900">รอตรวจสอบ</option>
                    <option value="processing" class="text-gray-900">กำลังดำเนินการ</option>
                    <option value="resolved" class="text-gray-900">แก้ไขแล้ว</option>
                </select>
                <button type="submit" class="bg-primary hover:bg-pink-600 text-white px-6 py-2.5 rounded-full text-base font-bold transition-colors shadow-sm">บันทึก</button>
            </form>

            <a href="#" id="md_reply_btn" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-blue-500 hover:bg-blue-600 text-white px-8 py-3.5 rounded-full text-base font-bold shadow-lg shadow-blue-500/30 transition-transform transform hover:-translate-y-0.5">
                <span class="material-icons-round text-[20px]">reply</span> ตอบกลับทางอีเมล
            </a>
        </div>
    </div>
</div>

<script>
    // 🟢 ระบบ Dark Mode
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }
    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        localStorage.setItem('theme', htmlEl.classList.contains('dark') ? 'dark' : 'light');
    }

    function openModalId(id) {
        const modal = document.getElementById(id);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('.modal-content').classList.remove('scale-95');
        }, 10);
    }
    function closeModal(id) {
        const modal = document.getElementById(id);
        modal.classList.add('opacity-0');
        modal.querySelector('.modal-content').classList.add('scale-95');
        setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 300);
    }

    // 🟢 เปิด Modal ดูรายละเอียดข้อความ
    function openComplaintModal(jsonData) {
        const c = JSON.parse(jsonData);
        
        document.getElementById('md_image').src = c.display_image;
        document.getElementById('md_name').innerText = c.name;
        document.getElementById('md_email').innerText = c.email;
        
        if (c.is_member) {
            document.getElementById('md_member_icon').classList.remove('hidden');
        } else {
            document.getElementById('md_member_icon').classList.add('hidden');
        }

        const dateObj = new Date(c.created_at);
        document.getElementById('md_date').innerText = dateObj.toLocaleDateString('th-TH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) + ' น.';
        
        document.getElementById('md_subject').innerText = c.subject || 'ไม่มีหัวข้อระบุ';
        document.getElementById('md_message').innerText = c.message;
        
        document.getElementById('md_form_msg_id').value = c.id;
        document.getElementById('md_status_select').value = c.status;

        // ปุ่มตอบกลับไปที่แอพ Mail
        document.getElementById('md_reply_btn').href = `mailto:${c.email}?subject=RE: ${encodeURIComponent(c.subject)}&body=${encodeURIComponent('\n\n\n-----------------\nข้อความเดิมของคุณ:\n' + c.message)}`;

        openModalId('complaintModal');
    }

    function confirmDelete(id) {
        Swal.fire({ 
            title: 'ยืนยันการลบข้อความ?', 
            text: "ข้อความนี้จะถูกลบออกจากระบบอย่างถาวร!", 
            icon: 'warning', 
            showCancelButton: true, 
            confirmButtonColor: '#ef4444', 
            cancelButtonColor: '#9CA3AF',
            confirmButtonText: 'ลบทิ้งเลย!', 
            cancelButtonText: 'ยกเลิก', 
            customClass: { popup: 'rounded-3xl dark:bg-gray-800 dark:text-white', confirmButton: 'rounded-full px-6 font-bold', cancelButton: 'rounded-full px-6 font-bold' }
        }).then((result) => { 
            if (result.isConfirmed) window.location.href = '?delete_id=' + id; 
        });
    }

    <?php if (isset($_SESSION['success_msg'])): ?>
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: '<?= $_SESSION['success_msg'] ?>', showConfirmButton: false, timer: 3000, customClass: { popup: 'rounded-2xl dark:bg-gray-800 dark:text-white' } });
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
</script>
</body>
</html>