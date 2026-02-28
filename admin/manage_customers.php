<?php
session_start();
require_once '../config/connectdbuser.php';

$adminName = $_SESSION['admin_username'] ?? 'Admin Nina'; 
$adminAvatar = "https://ui-avatars.com/api/?name=Admin&background=a855f7&color=fff";

// แจ้งเตือน Sidebar
$countPending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders` WHERE status='pending'"))['c'] ?? 0;

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
    // 1. อัปเดตข้อมูลทั่วไป
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

        // อัปเดต account
        mysqli_query($conn, "UPDATE `account` SET u_name = '$name', u_email = '$email' WHERE u_id = $edit_uid");
        // อัปเดต user
        mysqli_query($conn, "UPDATE `user` SET u_phone = '$phone', u_gender = '$gender', u_birthdate = $birthdate WHERE u_id = $edit_uid");
        
        // จัดการที่อยู่ (ถ้ามีก็อัปเดต ถ้าไม่มีก็เพิ่มใหม่ให้เป็นค่าเริ่มต้น)
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

    // 2. อัปเดตแต้มสะสม
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
// ดึงข้อมูลลูกค้า & คำนวณยอดซื้อ
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
    ORDER BY u.created_at DESC
";

if ($res = mysqli_query($conn, $sqlCustomers)) {
    while ($row = mysqli_fetch_assoc($res)) {
        $spent = (float)$row['total_spent'];
        
        if ($spent >= 10000) { $row['tier'] = 'Gold'; $stat_gold++; } 
        elseif ($spent >= 5000) { $row['tier'] = 'Silver'; $stat_silver++; } 
        else { $row['tier'] = 'Bronze'; $stat_bronze++; }

        if (strtotime($row['created_at']) >= strtotime('-30 days')) { $stat_new++; }
        
        // แยกข้อมูลที่อยู่เพื่อส่งเข้าฟอร์มแก้ไข
        $addrParts = explode('|', $row['raw_address'] ?? '|||');
        $row['addr_line'] = $addrParts[0] ?? '';
        $row['addr_dist'] = $addrParts[1] ?? '';
        $row['addr_prov'] = $addrParts[2] ?? '';
        $row['addr_zip'] = $addrParts[3] ?? '';
        $row['full_address'] = trim(str_replace('|', ' ', $row['raw_address'] ?? 'ไม่มีข้อมูลที่อยู่'));
        
        // จัดการ URL รูปภาพให้ถูกต้องก่อนส่งเป็น JSON
        if (!empty($row['u_image']) && file_exists("../uploads/" . $row['u_image'])) {
            $row['display_image'] = "../uploads/" . $row['u_image'];
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
<script> tailwind.config = { theme: { extend: { colors: { primary: "#ec2d88", "background-light": "#fff5f9", "text-main": "#1f2937" }, fontFamily: { sans: ["Prompt", "sans-serif"] } } } } </script>
<style> body { font-family: 'Prompt', sans-serif; } .sidebar-gradient { background: linear-gradient(180deg, #ffffff 0%, #fff5f9 100%); } .glass-panel { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(236, 45, 136, 0.1); } .nav-item-active { background-color: #ec2d88; color: white; box-shadow: 0 4px 12px rgba(236, 45, 136, 0.3); } .nav-item:hover:not(.nav-item-active) { background-color: #fce7f3; color: #ec2d88; } .custom-scroll::-webkit-scrollbar { width: 6px; } .custom-scroll::-webkit-scrollbar-thumb { background: #FBCFE8; border-radius: 10px; } </style>
</head>
<body class="bg-background-light font-sans text-text-main antialiased overflow-x-hidden selection:bg-primary selection:text-white">
<div class="flex min-h-screen w-full">
    
    <aside class="hidden lg:flex flex-col w-72 h-screen sticky top-0 border-r border-pink-100 sidebar-gradient p-6 justify-between z-20 shadow-sm">
        <div>
            <a href="../home.php" class="flex items-center gap-2 px-2 mb-10 hover:opacity-80 transition-opacity cursor-pointer"><span class="material-icons-round text-primary text-4xl">spa</span><span class="font-bold text-2xl tracking-tight text-primary">Lumina</span><span class="text-xs font-bold text-purple-500 bg-purple-100 px-2 py-0.5 rounded-full ml-1">Admin</span></a>
            <nav class="flex flex-col gap-2">
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-gray-500 transition-all group" href="dashboard.php"><span class="material-icons-round">dashboard</span><span class="font-medium text-[15px]">ภาพรวมระบบ</span></a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-gray-500 transition-all group" href="manage_products.php"><span class="material-icons-round">inventory_2</span><span class="font-medium text-[15px]">จัดการสินค้า</span></a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-gray-500 transition-all group" href="manage_orders.php">
                    <span class="material-icons-round">receipt_long</span><span class="font-medium text-[15px]">รายการสั่งซื้อ</span>
                    <?php if($countPending > 0): ?><span class="ml-auto bg-primary text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $countPending ?></span><?php endif; ?>
                </a>
                <a class="nav-item-active flex items-center gap-4 px-5 py-3.5 rounded-2xl transition-all group" href="#"><span class="material-icons-round">group</span><span class="font-bold text-[15px]">ข้อมูลลูกค้า</span></a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-gray-500 transition-all group" href="settings.php"><span class="material-icons-round">settings</span><span class="font-medium text-[15px]">ตั้งค่าระบบ</span></a>
            </nav>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0">
        <header class="flex items-center justify-between px-6 py-4 lg:px-10 lg:py-5 glass-panel sticky top-0 z-10">
            <div class="hidden md:flex flex-1 max-w-md relative group"><span class="material-icons-round absolute left-4 top-2.5 text-gray-400">search</span><input class="block w-full pl-12 pr-4 py-2.5 rounded-full border border-pink-100 bg-white shadow-sm text-sm outline-none" placeholder="ค้นหาชื่อลูกค้า, อีเมล, เบอร์โทร..." type="text"/></div>
            <div class="flex items-center gap-4 ml-auto"><img class="w-11 h-11 rounded-full object-cover" src="<?= $adminAvatar ?>"/></div>
        </header>

        <div class="p-6 lg:p-8 flex flex-col gap-6 max-w-[1600px] mx-auto w-full">
            <div class="flex justify-between items-center mb-2">
                <div><h1 class="text-3xl font-extrabold text-gray-800 tracking-tight">จัดการข้อมูลลูกค้า</h1><p class="text-sm text-gray-500 mt-1">สมาชิกทั้งหมด <span class="font-bold text-primary"><?= number_format($stat_total) ?></span> คน</p></div>
                <button class="bg-primary hover:bg-pink-600 text-white px-6 py-2.5 rounded-full font-bold text-sm shadow-lg shadow-primary/30 flex items-center gap-2"><span class="material-icons-round text-[18px]">campaign</span> ส่งจดหมายข่าว</button>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-3xl shadow-card border border-gray-100 flex items-center gap-4"><div class="w-12 h-12 rounded-full bg-yellow-50 flex items-center justify-center text-yellow-500"><span class="material-icons-round">workspace_premium</span></div><div><p class="text-[11px] text-gray-500 font-bold">สมาชิก Gold</p><h3 class="text-2xl font-extrabold text-gray-800"><?= number_format($stat_gold) ?></h3></div></div>
                <div class="bg-white p-5 rounded-3xl shadow-card border border-gray-100 flex items-center gap-4"><div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-500"><span class="material-icons-round">military_tech</span></div><div><p class="text-[11px] text-gray-500 font-bold">สมาชิก Silver</p><h3 class="text-2xl font-extrabold text-gray-800"><?= number_format($stat_silver) ?></h3></div></div>
                <div class="bg-white p-5 rounded-3xl shadow-card border border-gray-100 flex items-center gap-4"><div class="w-12 h-12 rounded-full bg-orange-50 flex items-center justify-center text-orange-600"><span class="material-icons-round">stars</span></div><div><p class="text-[11px] text-gray-500 font-bold">สมาชิก Bronze</p><h3 class="text-2xl font-extrabold text-gray-800"><?= number_format($stat_bronze) ?></h3></div></div>
                <div class="bg-white p-5 rounded-3xl shadow-card border border-pink-50 flex items-center gap-4"><div class="w-12 h-12 rounded-full bg-pink-50 flex items-center justify-center text-primary"><span class="material-icons-round">person_add</span></div><div><p class="text-[11px] text-gray-500 font-bold">สมัครใหม่ (เดือนนี้)</p><h3 class="text-2xl font-extrabold text-primary">+<?= number_format($stat_new) ?></h3></div></div>
            </div>

            <div class="bg-white rounded-[2rem] shadow-card overflow-hidden border border-pink-50 mt-4">
                <div class="overflow-x-auto min-h-[400px]">
                    <table class="w-full text-left border-collapse min-w-[1000px]">
                        <thead><tr class="bg-gray-50/80 text-gray-500 text-[12px] uppercase tracking-wider border-b border-gray-100"><th class="px-6 py-4 font-bold pl-8">ชื่อลูกค้า</th><th class="px-6 py-4 font-bold">อีเมล / เบอร์โทร</th><th class="px-6 py-4 font-bold">ระดับสมาชิก</th><th class="px-6 py-4 font-bold">ยอดซื้อสะสม</th><th class="px-6 py-4 font-bold">วันที่สมัคร</th><th class="px-6 py-4 font-bold text-center">สถานะ</th><th class="px-6 py-4 font-bold text-right pr-8">จัดการ</th></tr></thead>
                        <tbody class="text-sm divide-y divide-gray-50">
                            <?php foreach($customers as $c): 
                                $t_bg = $c['tier']=='Gold'?'bg-yellow-100 text-yellow-600':($c['tier']=='Silver'?'bg-gray-100 text-gray-600':'bg-orange-100 text-orange-700');
                                $json = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr class="hover:bg-pink-50/30 transition-colors group">
                                <td class="px-6 py-4 pl-8"><div class="flex items-center gap-3"><img src="<?= $c['display_image'] ?>" class="w-10 h-10 rounded-full object-cover shadow-sm border border-gray-100"><div class="flex flex-col"><span class="font-bold text-gray-900"><?= htmlspecialchars($c['u_name'] ?? $c['u_username']) ?></span><span class="text-[10px] text-gray-400">ID: CUS-<?= str_pad($c['u_id'], 4, '0', STR_PAD_LEFT) ?></span></div></div></td>
                                <td class="px-6 py-4"><div class="flex flex-col"><span class="text-gray-600 font-medium"><?= htmlspecialchars($c['u_email'] ?? '-') ?></span><span class="text-[11px] text-gray-400"><?= htmlspecialchars($c['u_phone'] ?: 'ไม่ระบุเบอร์โทร') ?></span></div></td>
                                <td class="px-6 py-4"><span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] font-bold <?= $t_bg ?>"><span class="material-icons-round text-[14px]">stars</span> <?= $c['tier'] ?></span></td>
                                <td class="px-6 py-4 font-bold text-gray-900 text-base">฿<?= number_format($c['total_spent'], 2) ?></td>
                                <td class="px-6 py-4 text-gray-500 font-medium text-xs"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                                <td class="px-6 py-4 text-center"><span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold text-green-600 bg-green-50 border border-green-100"><span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> ปกติ</span></td>
                                <td class="px-6 py-4 text-right pr-8">
                                    <button onclick="openCustomerModal('<?= $json ?>')" class="w-8 h-8 rounded-full text-gray-400 hover:text-blue-500 hover:bg-blue-50 transition-colors inline-flex items-center justify-center mr-1" title="ดูรายละเอียด"><span class="material-icons-round text-[18px]">visibility</span></button>
                                    <button onclick="confirmDelete(<?= $c['u_id'] ?>)" class="w-8 h-8 rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors inline-flex items-center justify-center" title="ลบลูกค้า"><span class="material-icons-round text-[18px]">delete</span></button>
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
    <div class="bg-white rounded-[2rem] w-full max-w-5xl h-auto max-h-[95vh] shadow-2xl flex flex-col overflow-hidden modal-content transform scale-95 transition-transform duration-300 border border-pink-50 relative">
        <div class="px-8 py-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <div class="flex items-center gap-2 text-sm text-gray-500 font-medium"><span class="text-primary font-bold">รายละเอียดลูกค้า</span></div>
            <button type="button" onclick="closeModal('customerModal')" class="w-8 h-8 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 shadow-sm transition-colors"><span class="material-icons-round text-[18px]">close</span></button>
        </div>

        <div class="flex-1 overflow-y-auto custom-scroll p-8 bg-gray-50/30">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <div class="lg:col-span-4 space-y-6">
                    <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100 text-center relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-b from-pink-50 to-white"></div>
                        <div class="relative z-10">
                            <img id="md_image" src="" class="w-24 h-24 rounded-full object-cover mx-auto shadow-md border-4 border-white mb-4">
                            <h2 class="text-xl font-bold text-gray-900 mb-1" id="md_name"></h2>
                            <div class="flex items-center justify-center gap-1.5 mb-2">
                                <span id="md_tier_badge" class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-bold"><span class="material-icons-round text-[14px]">stars</span> <span id="md_tier_text"></span></span>
                                <span class="text-xs text-gray-400">สมัคร <span id="md_join_date"></span></span>
                            </div>
                            <div class="flex justify-between items-center mt-6 pt-6 border-t border-gray-100">
                                <div class="text-left"><p class="text-xs text-gray-500 mb-1">ยอดรวมทั้งหมด</p><p class="text-lg font-extrabold text-gray-800" id="md_total_orders">0</p></div>
                                <div class="text-right"><p class="text-xs text-gray-500 mb-1">ยอดซื้อสะสม</p><p class="text-lg font-extrabold text-primary" id="md_total_spent">฿0.00</p></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-gray-800 flex items-center gap-2"><span class="material-icons-round text-primary text-[18px]">person</span> ข้อมูลส่วนตัว</h3>
                            <button onclick="openEditForm()" class="text-xs text-primary font-bold hover:underline">แก้ไข</button>
                        </div>
                        <div class="space-y-4">
                            <div class="flex gap-3"><div class="w-8 h-8 rounded-full bg-pink-50 flex items-center justify-center text-primary flex-shrink-0"><span class="material-icons-round text-[16px]">phone</span></div><div><p class="text-[10px] text-gray-400">เบอร์โทรศัพท์</p><p class="text-sm font-medium text-gray-700" id="md_phone">-</p></div></div>
                            <div class="flex gap-3"><div class="w-8 h-8 rounded-full bg-pink-50 flex items-center justify-center text-primary flex-shrink-0"><span class="material-icons-round text-[16px]">email</span></div><div><p class="text-[10px] text-gray-400">อีเมล</p><p class="text-sm font-medium text-gray-700 break-all" id="md_email">-</p></div></div>
                            <div class="flex gap-3"><div class="w-8 h-8 rounded-full bg-pink-50 flex items-center justify-center text-primary flex-shrink-0"><span class="material-icons-round text-[16px]">wc</span></div><div><p class="text-[10px] text-gray-400">เพศ</p><p class="text-sm font-medium text-gray-700" id="md_gender">-</p></div></div>
                            <div class="flex gap-3"><div class="w-8 h-8 rounded-full bg-pink-50 flex items-center justify-center text-primary flex-shrink-0"><span class="material-icons-round text-[16px]">cake</span></div><div><p class="text-[10px] text-gray-400">วันเกิด</p><p class="text-sm font-medium text-gray-700" id="md_birthdate">-</p></div></div>
                            <div class="flex gap-3"><div class="w-8 h-8 rounded-full bg-pink-50 flex items-center justify-center text-primary flex-shrink-0"><span class="material-icons-round text-[16px]">location_on</span></div><div><p class="text-[10px] text-gray-400">ที่อยู่หลัก</p><p class="text-sm font-medium text-gray-700 leading-relaxed" id="md_address">-</p></div></div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-8 space-y-6">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100"><div class="flex justify-between items-start mb-2"><span class="text-xs font-bold text-gray-500">ออเดอร์ทั้งหมด</span><span class="material-icons-round text-gray-300 text-[18px]">receipt_long</span></div><span class="text-2xl font-bold text-gray-800" id="md_stat_ord_count">0</span> <span class="text-xs text-gray-400">ครั้ง</span></div>
                        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100"><div class="flex justify-between items-start mb-2"><span class="text-xs font-bold text-gray-500">ยอดเฉลี่ยต่อบิล</span><span class="material-icons-round text-gray-300 text-[18px]">payments</span></div><span class="text-2xl font-bold text-gray-800" id="md_stat_avg">฿0</span></div>
                        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100"><div class="flex justify-between items-start mb-2"><span class="text-xs font-bold text-gray-500">สั่งซื้อล่าสุด</span><span class="material-icons-round text-gray-300 text-[18px]">schedule</span></div><span class="text-sm font-bold text-gray-800 mt-1 block" id="md_stat_last_date">-</span></div>
                    </div>

                    <div class="bg-gradient-to-r from-primary to-purple-500 rounded-2xl p-6 text-white shadow-lg shadow-primary/20 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div><h3 class="font-bold text-lg">จัดการแต้มสะสม</h3><p class="text-xs opacity-80 mt-1">ปรับปรุงคะแนนสะสมสำหรับลูกค้ารายนี้</p></div>
                        <form action="" method="POST" class="flex items-center bg-black/20 rounded-full p-1 backdrop-blur-sm w-full sm:w-auto">
                            <input type="hidden" name="action" value="update_points">
                            <input type="hidden" name="point_uid" id="form_point_uid">
                            <button type="button" onclick="document.getElementById('points_val').value--" class="w-8 h-8 flex items-center justify-center hover:bg-white/20 rounded-full transition shrink-0"><span class="material-icons-round text-sm">remove</span></button>
                            <input type="number" name="points_val" id="points_val" class="w-20 bg-transparent border-none text-center font-bold font-mono focus:ring-0 text-white appearance-none p-0" value="0">
                            <button type="button" onclick="document.getElementById('points_val').value++" class="w-8 h-8 flex items-center justify-center hover:bg-white/20 rounded-full transition shrink-0"><span class="material-icons-round text-sm">add</span></button>
                            <button type="submit" class="bg-white text-primary text-xs font-bold px-3 py-1.5 rounded-full ml-2 shadow-sm hover:bg-pink-50 shrink-0">บันทึก</button>
                        </form>
                    </div>

                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-50 pb-4"><h3 class="font-bold text-gray-800 flex items-center gap-2"><span class="material-icons-round text-primary text-[18px]">history</span> ประวัติสั่งซื้อ (ล่าสุด)</h3></div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead><tr class="text-gray-400 text-[11px] uppercase tracking-wider"><th class="pb-3 font-medium">หมายเลขคำสั่งซื้อ</th><th class="pb-3 font-medium">วันที่</th><th class="pb-3 font-medium">สถานะ</th><th class="pb-3 font-medium text-right">ยอดรวม</th></tr></thead>
                                <tbody class="text-sm" id="md_orders_body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="editCustomerModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[110] hidden items-center justify-center opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white rounded-[2rem] w-full max-w-2xl overflow-hidden shadow-2xl modal-content transform scale-95 transition-transform duration-300">
        <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2"><span class="material-icons-round text-primary">edit</span> แก้ไขข้อมูลลูกค้า</h2>
            <button type="button" onclick="closeModal('editCustomerModal')" class="w-8 h-8 rounded-full bg-white text-gray-400 hover:text-red-500 shadow-sm border border-gray-200 flex items-center justify-center"><span class="material-icons-round text-[18px]">close</span></button>
        </div>
        
        <form action="" method="POST" class="p-6 max-h-[70vh] overflow-y-auto custom-scroll space-y-5">
            <input type="hidden" name="action" value="edit_customer">
            <input type="hidden" name="edit_uid" id="edit_uid">
            
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-bold text-gray-700 mb-1">ชื่อ-นามสกุล</label><input type="text" name="edit_name" id="edit_name" required class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-primary/30"></div>
                <div><label class="block text-sm font-bold text-gray-700 mb-1">อีเมล</label><input type="email" name="edit_email" id="edit_email" required class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-primary/30"></div>
            </div>
            
            <div class="grid grid-cols-3 gap-4">
                <div><label class="block text-sm font-bold text-gray-700 mb-1">เบอร์โทรศัพท์</label><input type="text" name="edit_phone" id="edit_phone" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-primary/30"></div>
                <div><label class="block text-sm font-bold text-gray-700 mb-1">เพศ</label>
                    <select name="edit_gender" id="edit_gender" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-primary/30">
                        <option value="">ไม่ระบุ</option><option value="Male">ชาย</option><option value="Female">หญิง</option><option value="Other">อื่นๆ</option>
                    </select>
                </div>
                <div><label class="block text-sm font-bold text-gray-700 mb-1">วันเกิด</label><input type="date" name="edit_birthdate" id="edit_birthdate" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-primary/30 text-sm"></div>
            </div>

            <div class="border-t border-gray-100 pt-4">
                <h3 class="font-bold text-gray-800 mb-3 text-sm flex items-center gap-1"><span class="material-icons-round text-primary text-[16px]">location_on</span> ที่อยู่จัดส่งหลัก</h3>
                <div class="space-y-4">
                    <div><label class="block text-xs font-bold text-gray-500 mb-1">บ้านเลขที่/ซอย/ถนน</label><input type="text" name="edit_addr_line" id="edit_addr_line" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-primary/30 text-sm"></div>
                    <div class="grid grid-cols-3 gap-4">
                        <div><label class="block text-xs font-bold text-gray-500 mb-1">ตำบล/อำเภอ</label><input type="text" name="edit_district" id="edit_district" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-primary/30 text-sm"></div>
                        <div><label class="block text-xs font-bold text-gray-500 mb-1">จังหวัด</label><input type="text" name="edit_province" id="edit_province" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-primary/30 text-sm"></div>
                        <div><label class="block text-xs font-bold text-gray-500 mb-1">รหัสไปรษณีย์</label><input type="text" name="edit_zipcode" id="edit_zipcode" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 outline-none focus:ring-2 focus:ring-primary/30 text-sm"></div>
                    </div>
                </div>
            </div>

            <div class="pt-4 flex justify-end gap-3 border-t border-gray-100 mt-6">
                <button type="button" onclick="closeModal('editCustomerModal')" class="px-6 py-2.5 rounded-full font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition">ยกเลิก</button>
                <button type="submit" class="px-8 py-2.5 rounded-full font-bold text-white bg-primary hover:bg-pink-600 shadow-lg shadow-primary/30 transition">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentCustomerData = null; // เก็บข้อมูลชั่วคราวเผื่อตอนกดเปิด Edit Modal

    // เปิด Modal ทั่วไป
    function openModalId(id) {
        const modal = document.getElementById(id);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('.modal-content').classList.remove('scale-95');
        }, 10);
    }
    // ปิด Modal ทั่วไป
    function closeModal(id) {
        const modal = document.getElementById(id);
        modal.classList.add('opacity-0');
        modal.querySelector('.modal-content').classList.add('scale-95');
        setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 300);
    }

    // 🟢 แสดงข้อมูลใน Modal ดูข้อมูลลูกค้า 🟢
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
        
        // ฟอร์มตั้งค่าคะแนน
        document.getElementById('form_point_uid').value = cus.u_id;
        document.getElementById('points_val').value = cus.u_points;

        const dateObj = new Date(cus.created_at);
        document.getElementById('md_join_date').innerText = dateObj.toLocaleDateString('th-TH', { year: 'numeric', month: 'short', day: 'numeric' });

        // Tier
        const tb = document.getElementById('md_tier_badge');
        document.getElementById('md_tier_text').innerText = cus.tier;
        if (cus.tier === 'Gold') tb.className = 'inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-bold bg-yellow-100 text-yellow-600';
        else if (cus.tier === 'Silver') tb.className = 'inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600';
        else tb.className = 'inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-bold bg-orange-100 text-orange-700';

        // Stats
        document.getElementById('md_total_orders').innerText = cus.total_orders;
        document.getElementById('md_total_spent').innerText = '฿' + parseFloat(cus.total_spent).toLocaleString('th-TH', {minimumFractionDigits: 2});
        document.getElementById('md_stat_ord_count').innerText = cus.total_orders;
        document.getElementById('md_stat_avg').innerText = '฿' + (cus.total_orders > 0 ? (cus.total_spent / cus.total_orders) : 0).toLocaleString('th-TH', {maximumFractionDigits: 0});

        // Orders
        const tbody = document.getElementById('md_orders_body');
        tbody.innerHTML = '';
        if (cus.recent_orders && cus.recent_orders.length > 0) {
            document.getElementById('md_stat_last_date').innerText = new Date(cus.recent_orders[0].created_at).toLocaleDateString('th-TH');
            cus.recent_orders.forEach(ord => {
                let bc = 'bg-gray-100 text-gray-600', bt = ord.status;
                if(ord.status === 'pending') { bc='bg-orange-100 text-orange-600'; bt='รอชำระเงิน'; }
                else if(ord.status === 'processing') { bc='bg-blue-100 text-blue-600'; bt='กำลังจัดส่ง'; }
                else if(ord.status === 'shipped') { bc='bg-purple-100 text-purple-600'; bt='จัดส่งแล้ว'; }
                else if(ord.status === 'completed') { bc='bg-green-100 text-green-600'; bt='สำเร็จ'; }
                
                tbody.innerHTML += `<tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50"><td class="py-3 font-bold text-primary text-xs">#${ord.order_no}</td><td class="py-3 text-gray-500 text-xs">${new Date(ord.created_at).toLocaleDateString('th-TH')}</td><td class="py-3"><span class="px-2 py-0.5 rounded-md text-[10px] font-bold ${bc}">${bt}</span></td><td class="py-3 font-bold text-gray-800 text-right text-xs">฿${parseFloat(ord.total_amount).toLocaleString('th-TH')}</td></tr>`;
            });
        } else {
            document.getElementById('md_stat_last_date').innerText = '-';
            tbody.innerHTML = `<tr><td colspan="4" class="py-6 text-center text-gray-400 text-xs">ไม่มีประวัติการสั่งซื้อ</td></tr>`;
        }

        openModalId('customerModal');
    }

    // 🟢 เปิดฟอร์มแก้ไขข้อมูลลูกค้า 🟢
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

        // ปิดอันเก่า เปิดอันแก้
        closeModal('customerModal');
        setTimeout(() => { openModalId('editCustomerModal'); }, 300);
    }

    // 🟢 ลบ 🟢
    function confirmDelete(id) {
        Swal.fire({ title: 'ยืนยันการลบลูกค้า?', text: "ข้อมูลและประวัติที่เกี่ยวข้องจะหายไปถาวร!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'ลบข้อมูล!', cancelButtonText: 'ยกเลิก', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-full px-6', cancelButton: 'rounded-full px-6' }
        }).then((result) => { if (result.isConfirmed) window.location.href = '?delete_id=' + id; });
    }

    <?php if (isset($_SESSION['success_msg'])): ?>
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: '<?= $_SESSION['success_msg'] ?>', showConfirmButton: false, timer: 3000, customClass: { popup: 'rounded-2xl' } });
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
</script>
</body>
</html>