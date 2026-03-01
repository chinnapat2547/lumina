<?php
session_start();
require_once '../config/connectdbuser.php';

$adminName = $_SESSION['admin_username'] ?? 'Admin Nina'; 
$adminAvatar = "admin.jpg";

// ==========================================
// ส่วนดึงข้อมูลแจ้งเตือนสำหรับ Sidebar (ต้องมีทุกหน้า)
// ==========================================
$countPending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders` WHERE status='pending'"))['c'] ?? 0;
$newComplaints = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `contact_messages` WHERE status='pending' OR status IS NULL"))['c'] ?? 0;

// ==========================================
// การลบข้อมูลลูกค้า
// ==========================================
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    mysqli_query($conn, "DELETE FROM `cart` WHERE u_id = $del_id");
    mysqli_query($conn, "DELETE FROM `user_address` WHERE u_id = $del_id");
    mysqli_query($conn, "DELETE FROM `payment` WHERE u_id = $del_id"); 
    mysqli_query($conn, "DELETE FROM `user` WHERE u_id = $del_id");
    mysqli_query($conn, "DELETE FROM `account` WHERE u_id = $del_id");
    $_SESSION['success_msg'] = "ลบข้อมูลลูกค้าเรียบร้อยแล้ว!";
    header("Location: manage_customers.php");
    exit();
}

// ==========================================
// จัดการแก้ไขข้อมูลลูกค้า (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'edit_customer') {
        $edit_uid = (int)$_POST['edit_uid'];
        $name = mysqli_real_escape_string($conn, $_POST['edit_name']);
        $email = mysqli_real_escape_string($conn, $_POST['edit_email']);
        $phone = mysqli_real_escape_string($conn, $_POST['edit_phone']);
        $gender = mysqli_real_escape_string($conn, $_POST['edit_gender']);
        $birthdate = !empty($_POST['edit_birthdate']) ? "'" . mysqli_real_escape_string($conn, $_POST['edit_birthdate']) . "'" : "NULL";
        
        $addr_line = mysqli_real_escape_string($conn, $_POST['edit_addr_line']);
        $district = mysqli_real_escape_string($conn, $_POST['edit_district']);
        $province = mysqli_real_escape_string($conn, $_POST['edit_province']);
        $zipcode = mysqli_real_escape_string($conn, $_POST['edit_zipcode']);

        mysqli_query($conn, "UPDATE `account` SET u_name = '$name', u_email = '$email' WHERE u_id = $edit_uid");
        mysqli_query($conn, "UPDATE `user` SET u_phone = '$phone', u_gender = '$gender', u_birthdate = $birthdate WHERE u_id = $edit_uid");
        
        $chkAddr = mysqli_query($conn, "SELECT addr_id FROM `user_address` WHERE u_id = $edit_uid AND is_default = 1");
        if(mysqli_num_rows($chkAddr) > 0) {
            mysqli_query($conn, "UPDATE `user_address` SET recipient_name='$name', phone='$phone', address_line='$addr_line', district='$district', province='$province', zipcode='$zipcode' WHERE u_id=$edit_uid AND is_default=1");
        } else {
            mysqli_query($conn, "INSERT INTO `user_address` (u_id, addr_label, recipient_name, phone, address_line, district, province, zipcode, is_default) VALUES ($edit_uid, 'บ้าน', '$name', '$phone', '$addr_line', '$district', '$province', '$zipcode', 1)");
        }

        $_SESSION['success_msg'] = "บันทึกข้อมูลลูกค้าเรียบร้อยแล้ว!";
        header("Location: manage_customers.php");
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_points') {
        $pt_uid = (int)$_POST['point_uid'];
        $new_points = (int)$_POST['points_val'];
        mysqli_query($conn, "UPDATE `user` SET u_points = $new_points WHERE u_id = $pt_uid");
        $_SESSION['success_msg'] = "อัปเดตแต้มสะสมสำเร็จ!";
        header("Location: manage_customers.php");
        exit();
    }
}

// ==========================================
// 🟢 จัดการระบบค้นหา 🟢
// ==========================================
$search = trim($_GET['search'] ?? '');
$whereClause = "";
if ($search !== '') {
    $searchEsc = mysqli_real_escape_string($conn, $search);
    $whereClause = "WHERE a.u_username LIKE '%$searchEsc%' OR a.u_name LIKE '%$searchEsc%' OR a.u_email LIKE '%$searchEsc%' OR u.u_phone LIKE '%$searchEsc%'";
}

// ==========================================
// ดึงข้อมูลลูกค้า & คำนวณระดับ/ยอดซื้อ
// ==========================================
$customers = [];
$stat_gold = 0; $stat_silver = 0; $stat_bronze = 0; $stat_new = 0;

$sqlCustomers = "
    SELECT a.u_id, a.u_username, a.u_email, a.u_name, u.created_at, 
           u.u_image, u.u_phone, u.u_gender, u.u_birthdate, IFNULL(u.u_points, 0) as u_points,
           (SELECT CONCAT(address_line, '|', district, '|', province, '|', zipcode) 
            FROM user_address WHERE u_id = a.u_id ORDER BY is_default DESC LIMIT 1) as raw_address,
           (SELECT COUNT(order_id) FROM orders WHERE u_id = a.u_id) as total_orders,
           (SELECT SUM(total_amount) FROM orders WHERE u_id = a.u_id AND status != 'cancelled') as total_spent
    FROM `account` a
    LEFT JOIN `user` u ON a.u_id = u.u_id
    $whereClause
    ORDER BY u.created_at DESC
";

if ($res = mysqli_query($conn, $sqlCustomers)) {
    while ($row = mysqli_fetch_assoc($res)) {
        $pts = (int)$row['u_points'];
        
        // 🟢 แก้ที่ 3: ระดับสมาชิกตามแต้ม 🟢
        if ($pts >= 1000) { $row['tier'] = 'Gold'; $stat_gold++; } 
        elseif ($pts >= 100) { $row['tier'] = 'Silver'; $stat_silver++; } 
        else { $row['tier'] = 'Bronze'; $stat_bronze++; }

        if (strtotime($row['created_at']) >= strtotime('-30 days')) { $stat_new++; }
        
        $addrParts = explode('|', $row['raw_address'] ?? '|||');
        $row['addr_line'] = $addrParts[0] ?? '';
        $row['addr_dist'] = $addrParts[1] ?? '';
        $row['addr_prov'] = $addrParts[2] ?? '';
        $row['addr_zip'] = $addrParts[3] ?? '';
        $row['full_address'] = trim(str_replace('|', ' ', $row['raw_address'] ?? 'ไม่มีข้อมูลที่อยู่'));
        
        // 🟢 แก้ที่ 4: รูปโปรไฟล์จริงจากโฟลเดอร์ profile/uploads 🟢
        if (!empty($row['u_image']) && file_exists("../profile/uploads/" . $row['u_image'])) {
            $row['display_image'] = "../profile/uploads/" . $row['u_image'];
        } else {
            $row['display_image'] = "https://ui-avatars.com/api/?name=" . urlencode($row['u_name'] ?? $row['u_username'] ?? 'U') . "&background=fce7f3&color=ec2d88";
        }

        $row['recent_orders'] = [];
        $uid = $row['u_id'];
        $resOrd = mysqli_query($conn, "SELECT order_no, created_at, status, total_amount FROM `orders` WHERE u_id = $uid ORDER BY created_at DESC LIMIT 5");
        while($ord = mysqli_fetch_assoc($resOrd)) { $row['recent_orders'][] = $ord; }

        $customers[] = $row;
    }
}
$stat_total = count($customers);
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>ข้อมูลลูกค้า - Lumina Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    tailwind.config = {
        darkMode: "class", // 🟢 แก้ที่ 2: เปิดโหมดมืด
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
                    <?php if(isset($countPending) && $countPending > 0): ?>
                        <span class="flex items-center justify-center w-6 h-6 bg-primary text-white text-[12px] font-black rounded-full shadow-sm"><?= $countPending ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-item-active flex items-center gap-4 px-5 py-3.5 rounded-2xl transition-all duration-300 group" href="manage_customers.php">
                    <span class="material-icons-round">group</span>
                    <span class="font-bold text-[15px]">ข้อมูลลูกค้า</span>
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
                <p class="text-sm font-bold text-primary dark:text-pink-400">ต้องการความช่วยเหลือ?</p>
                <p class="text-xs text-text-muted dark:text-gray-400">ติดต่อทีมพัฒนาระบบ</p>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0">
        <header class="flex items-center justify-between px-6 py-4 lg:px-10 lg:py-5 glass-panel sticky top-0 z-10 transition-colors duration-300">
            <form method="GET" action="manage_customers.php" class="hidden md:flex flex-1 max-w-md relative group items-center">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <span class="material-icons-round text-gray-400 group-focus-within:text-primary transition-colors text-[20px]">search</span>
                </div>
                <input name="search" value="<?= htmlspecialchars($search) ?>" class="block w-full pl-12 pr-10 py-2.5 rounded-full border border-pink-100 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm text-sm placeholder-gray-400 dark:text-white focus:ring-2 focus:ring-primary/20 transition-all outline-none" placeholder="ค้นหาชื่อลูกค้า, อีเมล, เบอร์โทร..." type="text"/>
                <?php if (!empty($search)): ?>
                    <a href="manage_customers.php" class="absolute right-3 w-6 h-6 bg-pink-50 dark:bg-gray-700 hover:bg-red-100 dark:hover:bg-red-900/30 text-primary dark:text-pink-400 hover:text-red-500 rounded-full transition-colors flex items-center justify-center shadow-sm" title="ล้างการค้นหา">
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
                </div>
            </div>
        </header>

        <div class="p-6 lg:p-8 flex flex-col gap-6 max-w-[1600px] mx-auto w-full">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-4xl font-extrabold text-gray-800 dark:text-white tracking-tight flex items-center gap-3">
                        จัดการข้อมูลลูกค้า
                    </h1>
                    <span class="text-base font-bold bg-pink-100 dark:bg-pink-900/30 border border-pink-200 dark:border-pink-800/50 text-primary dark:text-pink-400 w-fit px-4 py-1.5 rounded-full shadow-sm mt-2 inline-block">
                        สมาชิกทั้งหมด <?= number_format($stat_total) ?> คน
                    </span>
                </div>
                </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 lg:gap-6 mt-2">
                <div class="bg-surface-white dark:bg-surface-dark p-6 rounded-3xl shadow-card border border-gray-100 dark:border-gray-700 flex items-center gap-4 hover:shadow-soft transition-shadow">
                    <div class="w-14 h-14 rounded-2xl bg-yellow-50 dark:bg-yellow-900/20 flex items-center justify-center text-yellow-500">
                        <span class="material-icons-round text-3xl">workspace_premium</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-semibold mb-1">สมาชิก Gold</p>
                        <h3 class="text-3xl font-extrabold text-gray-800 dark:text-white"><?= number_format($stat_gold) ?></h3>
                    </div>
                </div>
                <div class="bg-surface-white dark:bg-surface-dark p-6 rounded-3xl shadow-card border border-gray-100 dark:border-gray-700 flex items-center gap-4 hover:shadow-soft transition-shadow">
                    <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-500 dark:text-gray-400 border dark:border-gray-700">
                        <span class="material-icons-round text-3xl">military_tech</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-semibold mb-1">สมาชิก Silver</p>
                        <h3 class="text-3xl font-extrabold text-gray-800 dark:text-white"><?= number_format($stat_silver) ?></h3>
                    </div>
                </div>
                <div class="bg-surface-white dark:bg-surface-dark p-6 rounded-3xl shadow-card border border-gray-100 dark:border-gray-700 flex items-center gap-4 hover:shadow-soft transition-shadow">
                    <div class="w-14 h-14 rounded-2xl bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center text-orange-600">
                        <span class="material-icons-round text-3xl">stars</span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-semibold mb-1">สมาชิก Bronze</p>
                        <h3 class="text-3xl font-extrabold text-gray-800 dark:text-white"><?= number_format($stat_bronze) ?></h3>
                    </div>
                </div>
                <div class="bg-surface-white dark:bg-surface-dark p-6 rounded-3xl shadow-card border border-pink-100 dark:border-gray-700 flex items-center gap-4 hover:shadow-soft transition-shadow relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-50 dark:bg-pink-900/20 rounded-full z-0"></div>
                    <div class="relative z-10 w-14 h-14 rounded-2xl bg-pink-100 dark:bg-gray-800 flex items-center justify-center text-primary dark:border-gray-700">
                        <span class="material-icons-round text-3xl">person_add</span>
                    </div>
                    <div class="relative z-10">
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-semibold mb-1">สมัครใหม่ (เดือนนี้)</p>
                        <h3 class="text-3xl font-extrabold text-primary">+<?= number_format($stat_new) ?></h3>
                    </div>
                </div>
            </div>

            <div class="bg-surface-white dark:bg-surface-dark rounded-[2.5rem] shadow-card overflow-hidden border border-gray-100 dark:border-gray-700 mt-4 relative">
                <div class="overflow-x-auto min-h-[400px]">
                    <table class="w-full text-left border-collapse min-w-[1000px]">
                        <thead>
                            <tr class="bg-gray-50/80 dark:bg-gray-800/50 text-gray-600 dark:text-gray-400 text-[15px] uppercase tracking-wider border-b border-gray-100 dark:border-gray-700">
                                <th class="px-6 py-5 font-bold pl-8">ชื่อลูกค้า</th>
                                <th class="px-6 py-5 font-bold">อีเมล / เบอร์โทร</th>
                                <th class="px-6 py-5 font-bold text-center">ระดับสมาชิก</th>
                                <th class="px-6 py-5 font-bold text-center">ยอดซื้อสะสม</th>
                                <th class="px-6 py-5 font-bold text-center">วันที่สมัคร</th>
                                <th class="px-6 py-5 font-bold text-center">สถานะ</th>
                                <th class="px-6 py-5 font-bold text-center pr-8">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-50 dark:divide-gray-700/50">
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-20 text-center text-gray-400 dark:text-gray-500">
                                        <div class="flex flex-col items-center justify-center">
                                            <span class="material-icons-round text-7xl text-gray-200 dark:text-gray-600 mb-4">group_off</span>
                                            <p class="text-lg font-medium">ไม่พบข้อมูลลูกค้า</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            
                            <?php foreach($customers as $c): 
                                $t_bg = $c['tier']=='Gold'?'bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30':($c['tier']=='Silver'?'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300':'bg-orange-100 text-orange-700 dark:bg-orange-900/30');
                                $json = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr class="hover:bg-pink-50/30 dark:hover:bg-gray-800/30 transition-colors group">
                                <td class="px-6 py-5 pl-8">
                                    <div class="flex items-center gap-4">
                                        <div class="w-14 h-14 mx-auto rounded-[1rem] overflow-hidden border border-gray-100 dark:border-gray-700 shadow-sm bg-white dark:bg-gray-800 flex-shrink-0 p-0.5">
                                            <img src="<?= $c['display_image'] ?>" class="w-full h-full object-cover rounded-xl group-hover:scale-110 transition-transform duration-500">
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="font-extrabold text-gray-900 dark:text-white text-lg group-hover:text-primary transition-colors"><?= htmlspecialchars($c['u_name'] ?? $c['u_username']) ?></span>
                                            <span class="text-sm text-gray-400 font-mono bg-gray-50 dark:bg-gray-800 w-fit px-2 py-0.5 rounded-md border border-gray-100 dark:border-gray-700 mt-1">ID: CUS-<?= str_pad($c['u_id'], 4, '0', STR_PAD_LEFT) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex flex-col">
                                        <span class="text-gray-800 dark:text-gray-200 font-bold text-base"><?= htmlspecialchars($c['u_email'] ?? '-') ?></span>
                                        <span class="text-sm text-gray-500 dark:text-gray-400 mt-0.5"><?= htmlspecialchars($c['u_phone'] ?: 'ไม่ระบุเบอร์โทร') ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-[13px] font-bold border shadow-sm <?= $t_bg ?>">
                                        <span class="material-icons-round text-[16px]">stars</span> <?= $c['tier'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-center font-black text-primary text-xl">
                                    ฿<?= number_format($c['total_spent'], 2) ?>
                                </td>
                                <td class="px-6 py-5 text-gray-500 dark:text-gray-400 font-medium text-base text-center">
                                    <?= date('d M Y', strtotime($c['created_at'])) ?>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-[13px] font-bold text-green-600 bg-green-50 border border-green-100 dark:bg-green-900/30 dark:border-green-800 shadow-sm">
                                        <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> ปกติ
                                    </span>
                                </td>
                                <td class="px-6 py-5 pr-8 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button onclick="openCustomerModal('<?= $json ?>')" class="w-11 h-11 rounded-full text-gray-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-gray-700 transition-colors flex items-center justify-center shadow-sm border border-gray-100 dark:border-gray-600" title="ดูรายละเอียด">
                                            <span class="material-icons-round text-[24px]">visibility</span>
                                        </button>
                                        <button onclick="confirmDelete(<?= $c['u_id'] ?>)" class="w-11 h-11 rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-gray-700 transition-colors flex items-center justify-center shadow-sm border border-gray-100 dark:border-gray-600" title="ลบลูกค้า">
                                            <span class="material-icons-round text-[24px]">delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="customerModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden items-center justify-center opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-surface-white dark:bg-surface-dark rounded-[2.5rem] w-full max-w-5xl h-auto max-h-[95vh] shadow-2xl flex flex-col overflow-hidden modal-content transform scale-95 transition-transform duration-300 border border-gray-100 dark:border-gray-700 relative">
        <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800/50">
            <div class="flex items-center gap-3 text-lg text-gray-500 dark:text-gray-400 font-bold"><span class="material-icons-round text-primary">person</span> รายละเอียดลูกค้า</div>
            <button type="button" onclick="closeModal('customerModal')" class="w-11 h-11 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 shadow-sm transition-colors"><span class="material-icons-round text-[24px]">close</span></button>
        </div>

        <div class="flex-1 overflow-y-auto custom-scroll p-8 bg-gray-50/50 dark:bg-gray-900/50">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                
                <div class="lg:col-span-4 space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] p-8 shadow-sm border border-gray-100 dark:border-gray-700 text-center relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-28 bg-gradient-to-b from-pink-50 dark:from-pink-900/20 to-transparent"></div>
                        <div class="relative z-10">
                            <div class="w-32 h-32 mx-auto rounded-[1.5rem] overflow-hidden shadow-md border-4 border-white dark:border-gray-700 mb-5 p-1 bg-white dark:bg-gray-800">
                                <img id="md_image" src="" class="w-full h-full object-cover rounded-xl">
                            </div>
                            <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white mb-2" id="md_name"></h2>
                            <div class="flex items-center justify-center gap-2 mb-3">
                                <span id="md_tier_badge" class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-xs font-bold border shadow-sm"><span class="material-icons-round text-[16px]">stars</span> <span id="md_tier_text"></span></span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">สมัครเมื่อ <span id="md_join_date" class="font-bold"></span></p>
                            
                            <div class="flex justify-between items-center pt-6 border-t border-dashed border-gray-200 dark:border-gray-700">
                                <div class="text-left">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">ยอดรวมทั้งหมด</p>
                                    <p class="text-2xl font-black text-gray-800 dark:text-white"><span id="md_total_orders">0</span><span class="text-sm font-medium text-gray-400 ml-1">ครั้ง</span></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">ยอดซื้อสะสม</p>
                                    <p class="text-2xl font-black text-primary" id="md_total_spent">฿0.00</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] p-8 shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2"><span class="material-icons-round text-primary text-[22px]">info</span> ข้อมูลส่วนตัว</h3>
                            <button onclick="openEditForm()" class="text-sm text-blue-500 font-bold hover:text-blue-700 bg-blue-50 dark:bg-gray-700 px-3 py-1 rounded-full transition-colors flex items-center gap-1"><span class="material-icons-round text-[16px]">edit</span> แก้ไข</button>
                        </div>
                        <div class="space-y-5">
                            <div class="flex gap-4">
                                <div class="w-10 h-10 rounded-full bg-pink-50 dark:bg-gray-700 flex items-center justify-center text-primary flex-shrink-0 shadow-sm border border-pink-100 dark:border-gray-600"><span class="material-icons-round text-[20px]">phone</span></div>
                                <div class="flex flex-col justify-center"><p class="text-xs text-gray-400 dark:text-gray-500">เบอร์โทรศัพท์</p><p class="text-base font-bold text-gray-800 dark:text-gray-200" id="md_phone">-</p></div>
                            </div>
                            <div class="flex gap-4">
                                <div class="w-10 h-10 rounded-full bg-pink-50 dark:bg-gray-700 flex items-center justify-center text-primary flex-shrink-0 shadow-sm border border-pink-100 dark:border-gray-600"><span class="material-icons-round text-[20px]">email</span></div>
                                <div class="flex flex-col justify-center"><p class="text-xs text-gray-400 dark:text-gray-500">อีเมล</p><p class="text-base font-bold text-gray-800 dark:text-gray-200 break-all" id="md_email">-</p></div>
                            </div>
                            <div class="flex gap-4">
                                <div class="w-10 h-10 rounded-full bg-pink-50 dark:bg-gray-700 flex items-center justify-center text-primary flex-shrink-0 shadow-sm border border-pink-100 dark:border-gray-600"><span class="material-icons-round text-[20px]">wc</span></div>
                                <div class="flex flex-col justify-center"><p class="text-xs text-gray-400 dark:text-gray-500">เพศ</p><p class="text-base font-bold text-gray-800 dark:text-gray-200" id="md_gender">-</p></div>
                            </div>
                            <div class="flex gap-4">
                                <div class="w-10 h-10 rounded-full bg-pink-50 dark:bg-gray-700 flex items-center justify-center text-primary flex-shrink-0 shadow-sm border border-pink-100 dark:border-gray-600"><span class="material-icons-round text-[20px]">cake</span></div>
                                <div class="flex flex-col justify-center"><p class="text-xs text-gray-400 dark:text-gray-500">วันเกิด</p><p class="text-base font-bold text-gray-800 dark:text-gray-200" id="md_birthdate">-</p></div>
                            </div>
                            <div class="flex gap-4">
                                <div class="w-10 h-10 rounded-full bg-pink-50 dark:bg-gray-700 flex items-center justify-center text-primary flex-shrink-0 shadow-sm border border-pink-100 dark:border-gray-600"><span class="material-icons-round text-[20px]">location_on</span></div>
                                <div class="flex flex-col justify-center w-full"><p class="text-xs text-gray-400 dark:text-gray-500">ที่อยู่หลัก</p><p class="text-sm font-medium text-gray-700 dark:text-gray-300 leading-relaxed bg-gray-50 dark:bg-gray-700/50 p-3 rounded-xl mt-1 border border-gray-100 dark:border-gray-600" id="md_address">-</p></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-8 space-y-6">
                    <div class="grid grid-cols-3 gap-6">
                        <div class="bg-white dark:bg-gray-800 rounded-[2rem] p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                            <div class="flex justify-between items-start mb-3"><span class="text-sm font-bold text-gray-500 dark:text-gray-400">ออเดอร์ทั้งหมด</span><div class="w-10 h-10 rounded-full bg-blue-50 dark:bg-gray-700 flex items-center justify-center"><span class="material-icons-round text-blue-500">receipt_long</span></div></div>
                            <span class="text-3xl font-black text-gray-800 dark:text-white" id="md_stat_ord_count">0</span> <span class="text-sm text-gray-400 font-medium">ครั้ง</span>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-[2rem] p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                            <div class="flex justify-between items-start mb-3"><span class="text-sm font-bold text-gray-500 dark:text-gray-400">ยอดเฉลี่ยต่อบิล</span><div class="w-10 h-10 rounded-full bg-green-50 dark:bg-gray-700 flex items-center justify-center"><span class="material-icons-round text-green-500">payments</span></div></div>
                            <span class="text-3xl font-black text-gray-800 dark:text-white" id="md_stat_avg">฿0</span>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-[2rem] p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                            <div class="flex justify-between items-start mb-3"><span class="text-sm font-bold text-gray-500 dark:text-gray-400">สั่งซื้อล่าสุด</span><div class="w-10 h-10 rounded-full bg-purple-50 dark:bg-gray-700 flex items-center justify-center"><span class="material-icons-round text-purple-500">schedule</span></div></div>
                            <span class="text-lg font-bold text-gray-800 dark:text-white mt-1 block" id="md_stat_last_date">-</span>
                        </div>
                    </div>

                    <div class="bg-gradient-to-r from-primary to-purple-500 rounded-[2rem] p-8 text-white shadow-lg shadow-primary/20 flex flex-col sm:flex-row justify-between items-center gap-6">
                        <div>
                            <h3 class="font-extrabold text-2xl flex items-center gap-2"><span class="material-icons-round text-3xl">generating_tokens</span> จัดการแต้มสะสม</h3>
                            <p class="text-sm opacity-90 mt-1">ปรับปรุงคะแนนเพื่อเปลี่ยนระดับสมาชิก (Gold: 1,000+)</p>
                        </div>
                        <form action="" method="POST" class="flex items-center bg-black/20 rounded-full p-1.5 backdrop-blur-sm w-full sm:w-auto shadow-inner border border-white/10">
                            <input type="hidden" name="action" value="update_points">
                            <input type="hidden" name="point_uid" id="form_point_uid">
                            <button type="button" onclick="document.getElementById('points_val').value--" class="w-10 h-10 flex items-center justify-center hover:bg-white/20 rounded-full transition shrink-0"><span class="material-icons-round">remove</span></button>
                            <input type="number" name="points_val" id="points_val" class="w-24 bg-transparent border-none text-center font-black text-2xl focus:ring-0 text-white appearance-none p-0" value="0">
                            <button type="button" onclick="document.getElementById('points_val').value++" class="w-10 h-10 flex items-center justify-center hover:bg-white/20 rounded-full transition shrink-0"><span class="material-icons-round">add</span></button>
                            <button type="submit" class="bg-white text-primary text-sm font-extrabold px-6 py-3 rounded-full ml-3 shadow-md hover:bg-pink-50 transition transform hover:-translate-y-0.5 shrink-0">บันทึก</button>
                        </form>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-[2rem] p-8 shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="flex justify-between items-center mb-6 border-b border-gray-50 dark:border-gray-700 pb-4">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2"><span class="material-icons-round text-primary text-[22px]">history</span> ประวัติสั่งซื้อ (5 รายการล่าสุด)</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-gray-400 dark:text-gray-500 text-[13px] uppercase tracking-wider">
                                        <th class="pb-4 font-bold">หมายเลขคำสั่งซื้อ</th>
                                        <th class="pb-4 font-bold">วันที่</th>
                                        <th class="pb-4 font-bold text-center">สถานะ</th>
                                        <th class="pb-4 font-bold text-right">ยอดรวม</th>
                                    </tr>
                                </thead>
                                <tbody class="text-base" id="md_orders_body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="editCustomerModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[110] hidden items-center justify-center opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-surface-white dark:bg-surface-dark rounded-[2.5rem] w-full max-w-2xl overflow-hidden shadow-2xl modal-content transform scale-95 transition-transform duration-300 border border-gray-100 dark:border-gray-700">
        <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800/50">
            <h2 class="text-2xl font-extrabold text-gray-800 dark:text-white flex items-center gap-2"><span class="material-icons-round text-primary">edit_note</span> แก้ไขข้อมูลลูกค้า</h2>
            <button type="button" onclick="closeModal('editCustomerModal')" class="w-10 h-10 rounded-full bg-white dark:bg-gray-800 text-gray-400 hover:text-red-500 shadow-sm border border-gray-200 dark:border-gray-600 flex items-center justify-center transition-colors"><span class="material-icons-round text-[20px]">close</span></button>
        </div>
        
        <form action="" method="POST" class="p-8 max-h-[75vh] overflow-y-auto custom-scroll space-y-6">
            <input type="hidden" name="action" value="edit_customer">
            <input type="hidden" name="edit_uid" id="edit_uid">
            
            <div class="grid grid-cols-2 gap-5">
                <div><label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2 ml-1">ชื่อ-นามสกุล</label><input type="text" name="edit_name" id="edit_name" required class="w-full px-5 py-3.5 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-primary/30 focus:bg-white dark:focus:bg-gray-700 transition shadow-sm text-base"></div>
                <div><label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2 ml-1">อีเมล</label><input type="email" name="edit_email" id="edit_email" required class="w-full px-5 py-3.5 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-primary/30 focus:bg-white dark:focus:bg-gray-700 transition shadow-sm text-base"></div>
            </div>
            
            <div class="grid grid-cols-3 gap-5">
                <div><label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2 ml-1">เบอร์โทรศัพท์</label><input type="text" name="edit_phone" id="edit_phone" class="w-full px-5 py-3.5 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-primary/30 focus:bg-white dark:focus:bg-gray-700 transition shadow-sm text-base"></div>
                <div><label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2 ml-1">เพศ</label>
                    <select name="edit_gender" id="edit_gender" class="w-full px-5 py-3.5 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-primary/30 focus:bg-white dark:focus:bg-gray-700 transition shadow-sm text-base cursor-pointer">
                        <option value="">ไม่ระบุ</option><option value="Male">ชาย</option><option value="Female">หญิง</option><option value="Other">อื่นๆ</option>
                    </select>
                </div>
                <div><label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2 ml-1">วันเกิด</label><input type="date" name="edit_birthdate" id="edit_birthdate" class="w-full px-5 py-3.5 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-primary/30 focus:bg-white dark:focus:bg-gray-700 transition shadow-sm text-base"></div>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-700 pt-6 mt-2">
                <h3 class="font-bold text-gray-800 dark:text-white mb-4 text-base flex items-center gap-2"><span class="material-icons-round text-primary text-[20px]">location_on</span> ที่อยู่จัดส่งหลัก</h3>
                <div class="space-y-5">
                    <div><label class="block text-sm font-bold text-gray-500 dark:text-gray-400 mb-2 ml-1">บ้านเลขที่/ซอย/ถนน</label><input type="text" name="edit_addr_line" id="edit_addr_line" class="w-full px-5 py-3.5 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-primary/30 focus:bg-white dark:focus:bg-gray-700 transition shadow-sm text-base"></div>
                    <div class="grid grid-cols-3 gap-5">
                        <div><label class="block text-sm font-bold text-gray-500 dark:text-gray-400 mb-2 ml-1">ตำบล/อำเภอ</label><input type="text" name="edit_district" id="edit_district" class="w-full px-5 py-3.5 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-primary/30 focus:bg-white dark:focus:bg-gray-700 transition shadow-sm text-base"></div>
                        <div><label class="block text-sm font-bold text-gray-500 dark:text-gray-400 mb-2 ml-1">จังหวัด</label><input type="text" name="edit_province" id="edit_province" class="w-full px-5 py-3.5 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-primary/30 focus:bg-white dark:focus:bg-gray-700 transition shadow-sm text-base"></div>
                        <div><label class="block text-sm font-bold text-gray-500 dark:text-gray-400 mb-2 ml-1">รหัสไปรษณีย์</label><input type="text" name="edit_zipcode" id="edit_zipcode" class="w-full px-5 py-3.5 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-primary/30 focus:bg-white dark:focus:bg-gray-700 transition shadow-sm text-base"></div>
                    </div>
                </div>
            </div>

            <div class="pt-6 flex justify-end gap-4 border-t border-gray-100 dark:border-gray-700 mt-8">
                <button type="button" onclick="closeModal('editCustomerModal')" class="px-8 py-3.5 rounded-full font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600 transition shadow-sm text-base border border-gray-200 dark:border-gray-600">ยกเลิก</button>
                <button type="submit" class="px-10 py-3.5 rounded-full font-bold text-white bg-primary hover:bg-pink-600 shadow-lg shadow-primary/30 transition transform hover:-translate-y-0.5 text-base">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<script>
    // 🟢 ระบบ Dark Mode 🟢
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }
    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        localStorage.setItem('theme', htmlEl.classList.contains('dark') ? 'dark' : 'light');
    }

    let currentCustomerData = null;

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

    function openCustomerModal(jsonData) {
        currentCustomerData = JSON.parse(jsonData);
        const cus = currentCustomerData;
        
        document.getElementById('md_image').src = cus.display_image;
        document.getElementById('md_name').innerText = cus.u_name || cus.u_username;
        document.getElementById('md_email').innerText = cus.u_email || '-';
        document.getElementById('md_phone').innerText = cus.u_phone || '-';
        document.getElementById('md_gender').innerText = cus.u_gender || '-';
        document.getElementById('md_birthdate').innerText = cus.u_birthdate || '-';
        document.getElementById('md_address').innerText = cus.full_address;
        
        document.getElementById('form_point_uid').value = cus.u_id;
        document.getElementById('points_val').value = cus.u_points;

        const dateObj = new Date(cus.created_at);
        document.getElementById('md_join_date').innerText = dateObj.toLocaleDateString('th-TH', { year: 'numeric', month: 'short', day: 'numeric' });

        const tb = document.getElementById('md_tier_badge');
        document.getElementById('md_tier_text').innerText = cus.tier;
        if (cus.tier === 'Gold') tb.className = 'inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-xs font-bold border shadow-sm bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30 border-yellow-200 dark:border-yellow-800';
        else if (cus.tier === 'Silver') tb.className = 'inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-xs font-bold border shadow-sm bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700';
        else tb.className = 'inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-xs font-bold border shadow-sm bg-orange-100 text-orange-700 dark:bg-orange-900/30 border-orange-200 dark:border-orange-800';

        document.getElementById('md_total_orders').innerText = cus.total_orders;
        document.getElementById('md_total_spent').innerText = '฿' + parseFloat(cus.total_spent).toLocaleString('th-TH', {minimumFractionDigits: 2});
        document.getElementById('md_stat_ord_count').innerText = cus.total_orders;
        document.getElementById('md_stat_avg').innerText = '฿' + (cus.total_orders > 0 ? (cus.total_spent / cus.total_orders) : 0).toLocaleString('th-TH', {maximumFractionDigits: 0});

        const tbody = document.getElementById('md_orders_body');
        tbody.innerHTML = '';
        if (cus.recent_orders && cus.recent_orders.length > 0) {
            document.getElementById('md_stat_last_date').innerText = new Date(cus.recent_orders[0].created_at).toLocaleDateString('th-TH');
            cus.recent_orders.forEach(ord => {
                let bc = 'bg-gray-100 text-gray-600 dark:bg-gray-800 border-gray-200', bt = ord.status;
                if(ord.status === 'pending') { bc='bg-orange-100 text-orange-600 border-orange-200'; bt='รอชำระเงิน'; }
                else if(ord.status === 'processing') { bc='bg-blue-100 text-blue-600 border-blue-200'; bt='กำลังจัดส่ง'; }
                else if(ord.status === 'shipped') { bc='bg-purple-100 text-purple-600 border-purple-200'; bt='จัดส่งแล้ว'; }
                else if(ord.status === 'completed') { bc='bg-green-100 text-green-600 border-green-200'; bt='สำเร็จ'; }
                
                tbody.innerHTML += `<tr class="border-b border-gray-50 dark:border-gray-700/50 last:border-0 hover:bg-gray-50/50 dark:hover:bg-gray-800/30"><td class="py-4 font-bold text-primary text-sm">#${ord.order_no}</td><td class="py-4 text-gray-500 dark:text-gray-400 text-sm font-medium">${new Date(ord.created_at).toLocaleDateString('th-TH')}</td><td class="py-4 text-center"><span class="px-3 py-1 rounded-full text-[11px] font-bold border shadow-sm ${bc}">${bt}</span></td><td class="py-4 font-black text-gray-800 dark:text-white text-right text-base">฿${parseFloat(ord.total_amount).toLocaleString('th-TH')}</td></tr>`;
            });
        } else {
            document.getElementById('md_stat_last_date').innerText = '-';
            tbody.innerHTML = `<tr><td colspan="4" class="py-8 text-center text-gray-400 text-sm font-medium">ไม่มีประวัติการสั่งซื้อ</td></tr>`;
        }

        openModalId('customerModal');
    }

    function openEditForm() {
        if(!currentCustomerData) return;
        const c = currentCustomerData;
        
        document.getElementById('edit_uid').value = c.u_id;
        document.getElementById('edit_name').value = c.u_name || c.u_username;
        document.getElementById('edit_email').value = c.u_email;
        document.getElementById('edit_phone').value = c.u_phone || '';
        document.getElementById('edit_gender').value = c.u_gender || '';
        document.getElementById('edit_birthdate').value = c.u_birthdate || '';
        
        document.getElementById('edit_addr_line').value = c.addr_line || '';
        document.getElementById('edit_district').value = c.addr_dist || '';
        document.getElementById('edit_province').value = c.addr_prov || '';
        document.getElementById('edit_zipcode').value = c.addr_zip || '';

        closeModal('customerModal');
        setTimeout(() => { openModalId('editCustomerModal'); }, 300);
    }

    function confirmDelete(id) {
        Swal.fire({ title: 'ยืนยันการลบลูกค้า?', text: "ข้อมูลและประวัติที่เกี่ยวข้องจะหายไปถาวร!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'ลบข้อมูล!', cancelButtonText: 'ยกเลิก', customClass: { popup: 'rounded-3xl dark:bg-gray-800 dark:text-white', confirmButton: 'rounded-full px-6 font-bold', cancelButton: 'rounded-full px-6 font-bold' }
        }).then((result) => { if (result.isConfirmed) window.location.href = '?delete_id=' + id; });
    }

    <?php if (isset($_SESSION['success_msg'])): ?>
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: '<?= $_SESSION['success_msg'] ?>', showConfirmButton: false, timer: 3000, customClass: { popup: 'rounded-2xl dark:bg-gray-800 dark:text-white' } });
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
</script>
</body>
</html>