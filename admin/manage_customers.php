<?php
session_start();
require_once '../config/connectdbuser.php';

// ==========================================
// 1. ตรวจสอบสิทธิ์ (จำลอง Admin)
// ==========================================
// if (!isset($_SESSION['admin_id'])) { header("Location: ../auth/login.php"); exit(); }
$adminName = $_SESSION['admin_username'] ?? 'Admin Nina'; 
$adminAvatar = "https://ui-avatars.com/api/?name=Admin&background=a855f7&color=fff";

// โชว์แจ้งเตือนออเดอร์ใน Sidebar
$countPending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders` WHERE status='pending'"))['c'];

// ==========================================
// 2. จัดการลบข้อมูลลูกค้า (GET)
// ==========================================
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    
    // ลบข้อมูลที่เกี่ยวข้อง (ควรเช็ค FK constraints ในฐานข้อมูลด้วย)
    mysqli_query($conn, "DELETE FROM `cart` WHERE u_id = $del_id");
    mysqli_query($conn, "DELETE FROM `user_address` WHERE u_id = $del_id");
    mysqli_query($conn, "DELETE FROM `payment` WHERE u_id = $del_id"); // แก้จาก user_card เป็น payment
    mysqli_query($conn, "DELETE FROM `user` WHERE u_id = $del_id");
    mysqli_query($conn, "DELETE FROM `account` WHERE u_id = $del_id");
    // หมายเหตุ: โดยปกติรายการสั่งซื้อ (orders) จะไม่ถูกลบเมื่อลบลูกค้า แต่จะเซ็ต u_id = NULL หรือปล่อยไว้เพื่อเก็บสถิติ
    
    $_SESSION['success_msg'] = "ลบข้อมูลลูกค้าเรียบร้อยแล้ว!";
    header("Location: manage_customers.php");
    exit();
}

// ==========================================
// 3. ดึงข้อมูลลูกค้า & คำนวณยอดซื้อ
// ==========================================
$customers = [];
$stat_gold = 0;
$stat_silver = 0;
$stat_bronze = 0;
$stat_new = 0; // สมัครในเดือนนี้

// คำสั่ง SQL ดึงข้อมูลลูกค้า + ยอดสั่งซื้อรวม + จำนวนออเดอร์ (แก้ให้ตรงกับฐานข้อมูลจริง)
$sqlCustomers = "
    SELECT a.u_id, a.u_username, a.u_email, a.u_name, u.created_at, 
           u.u_image, u.u_phone, 
           (SELECT CONCAT(address_line, ' ', district, ' ', province, ' ', zipcode) 
            FROM user_address WHERE u_id = a.u_id ORDER BY is_default DESC LIMIT 1) as u_address,
           COUNT(o.order_id) as total_orders,
           SUM(CASE WHEN o.status != 'cancelled' THEN o.total_amount ELSE 0 END) as total_spent
    FROM `account` a
    LEFT JOIN `user` u ON a.u_id = u.u_id
    LEFT JOIN `orders` o ON a.u_id = o.u_id
    GROUP BY a.u_id, a.u_username, a.u_email, a.u_name, u.created_at, u.u_image, u.u_phone
    ORDER BY u.created_at DESC
";

if ($res = mysqli_query($conn, $sqlCustomers)) {
    while ($row = mysqli_fetch_assoc($res)) {
        $spent = (float)$row['total_spent'];
        
        // คำนวณระดับสมาชิก (Tier) จำลอง
        if ($spent >= 10000) {
            $row['tier'] = 'Gold';
            $stat_gold++;
        } elseif ($spent >= 5000) {
            $row['tier'] = 'Silver';
            $stat_silver++;
        } else {
            $row['tier'] = 'Bronze';
            $stat_bronze++;
        }

        // เช็คสมาชิกใหม่ (ภายใน 30 วัน)
        if (strtotime($row['created_at']) >= strtotime('-30 days')) {
            $stat_new++;
        }

        // ดึงประวัติการสั่งซื้อล่าสุด 5 รายการของคนนี้เพื่อไปแสดงใน Modal
        $recent_orders = [];
        $uid = $row['u_id'];
        $sqlOrd = "SELECT order_no, created_at, status, total_amount FROM `orders` WHERE u_id = $uid ORDER BY created_at DESC LIMIT 5";
        $resOrd = mysqli_query($conn, $sqlOrd);
        while($ord = mysqli_fetch_assoc($resOrd)) {
            $recent_orders[] = $ord;
        }
        $row['recent_orders'] = $recent_orders;

        $customers[] = $row;
    }
}
$stat_total = count($customers);

// ฟังก์ชันป้ายสถานะออเดอร์ใน Modal
function getStatusBadge($status) {
    $badges = [
        'pending' => ['text' => 'รอชำระเงิน', 'class' => 'bg-orange-100 text-orange-600'],
        'processing' => ['text' => 'กำลังจัดส่ง', 'class' => 'bg-blue-100 text-blue-600'],
        'shipped' => ['text' => 'จัดส่งแล้ว', 'class' => 'bg-purple-100 text-purple-600'],
        'completed' => ['text' => 'สำเร็จแล้ว', 'class' => 'bg-green-100 text-green-600'],
        'cancelled' => ['text' => 'ยกเลิก', 'class' => 'bg-red-100 text-red-600']
    ];
    return $badges[$status] ?? ['text' => 'ไม่ทราบ', 'class' => 'bg-gray-100 text-gray-600'];
}
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>ข้อมูลลูกค้า - Lumina Admin</title>

<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: "#ec2d88", 
                    "primary-light": "#fce7f3",
                    secondary: "#a855f7",
                    "background-light": "#fff5f9", 
                    "surface-white": "#ffffff",
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
    .glass-panel { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(236, 45, 136, 0.1); }
    .nav-item-active { background-color: #ec2d88; color: white; box-shadow: 0 4px 12px rgba(236, 45, 136, 0.3); }
    .nav-item:hover:not(.nav-item-active) { background-color: #fce7f3; color: #ec2d88; }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: #FBCFE8; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #ec2d88; }
    .custom-scroll::-webkit-scrollbar { width: 4px; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }
</style>
</head>
<body class="bg-background-light font-sans text-text-main antialiased overflow-x-hidden selection:bg-primary selection:text-white">
<div class="flex min-h-screen w-full">
    
    <aside class="hidden lg:flex flex-col w-72 h-screen sticky top-0 border-r border-pink-100 sidebar-gradient p-6 justify-between z-20 shadow-sm">
        <div>
            <a href="../home.php" class="flex items-center gap-2 px-2 mb-10 hover:opacity-80 transition-opacity cursor-pointer">
                <span class="material-icons-round text-primary text-4xl">spa</span>
                <span class="font-bold text-2xl tracking-tight text-primary">Lumina</span>
                <span class="text-xs font-bold text-purple-500 bg-purple-100 px-2 py-0.5 rounded-full ml-1">Admin</span>
            </a>
            <nav class="flex flex-col gap-2">
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="dashboard.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">dashboard</span>
                    <span class="font-medium text-[15px]">ภาพรวมระบบ</span>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="manage_products.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">inventory_2</span>
                    <span class="font-medium text-[15px]">จัดการสินค้า</span>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="manage_orders.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">receipt_long</span>
                    <span class="font-medium text-[15px]">รายการสั่งซื้อ</span>
                    <?php if($countPending > 0): ?>
                        <span class="ml-auto bg-primary text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm"><?= $countPending ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-item-active flex items-center gap-4 px-5 py-3.5 rounded-2xl transition-all duration-300 group" href="manage_customers.php">
                    <span class="material-icons-round">group</span>
                    <span class="font-bold text-[15px]">ข้อมูลลูกค้า</span>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="settings.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">settings</span>
                    <span class="font-medium text-[15px]">ตั้งค่าระบบ</span>
                </a>
            </nav>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0">
        
        <header class="flex items-center justify-between px-6 py-4 lg:px-10 lg:py-5 glass-panel sticky top-0 z-10">
            <div class="flex items-center gap-4 lg:hidden">
                <button class="p-2 text-text-main hover:bg-pink-50 rounded-xl transition-colors"><span class="material-icons-round">menu</span></button>
                <span class="font-bold text-xl text-primary flex items-center gap-1"><span class="material-icons-round">spa</span> Lumina</span>
            </div>
            
            <div class="hidden md:flex flex-1 max-w-md relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <span class="material-icons-round text-gray-400 group-focus-within:text-primary transition-colors text-[20px]">search</span>
                </div>
                <input class="block w-full pl-12 pr-4 py-2.5 rounded-full border border-pink-100 bg-white shadow-sm text-sm placeholder-gray-400 focus:ring-2 focus:ring-primary/20 transition-all outline-none" placeholder="ค้นหาชื่อลูกค้า, อีเมล, เบอร์โทร..." type="text"/>
            </div>
            
            <div class="flex items-center gap-4 lg:gap-6 ml-auto">
                <div class="relative group flex items-center">
                    <a href="#" class="block w-11 h-11 rounded-full bg-gradient-to-tr from-purple-400 to-indigo-400 p-[2px] shadow-sm hover:shadow-glow hover:scale-105 transition-all cursor-pointer">
                        <div class="bg-white rounded-full p-[2px] w-full h-full">
                            <img alt="Admin" class="w-full h-full rounded-full object-cover" src="<?= $adminAvatar ?>"/>
                        </div>
                    </a>
                    <div class="absolute right-0 hidden pt-4 top-full w-[300px] z-50 group-hover:block cursor-default">
                        <div class="bg-white rounded-3xl shadow-soft border border-pink-100 overflow-hidden p-5 relative">
                            <div class="text-center mb-4"><span class="text-sm font-bold text-purple-500 bg-purple-50 px-3 py-1 rounded-full">Administrator Mode</span></div>
                            <div class="flex justify-center relative mb-3">
                                <div class="rounded-full p-[3px] bg-purple-500 shadow-md">
                                    <div class="bg-white rounded-full p-[2px] w-16 h-16"><img src="<?= $adminAvatar ?>" alt="Profile" class="w-full h-full rounded-full object-cover"></div>
                                </div>
                            </div>
                            <div class="text-center mb-6"><h3 class="text-[20px] font-bold text-gray-800">สวัสดี, คุณ <?= htmlspecialchars($adminName) ?></h3></div>
                            <div class="flex flex-col gap-3">
                                <a href="../auth/logout.php" class="w-full flex items-center justify-center gap-2 bg-white border-2 border-red-500 hover:bg-red-500 hover:text-white rounded-full py-2.5 transition text-[14px] font-semibold text-red-500">
                                    <span class="material-icons-round text-[18px]">logout</span> ออกจากระบบ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="p-6 lg:p-8 flex flex-col gap-6 max-w-[1600px] mx-auto w-full">
            
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-2">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-800 tracking-tight flex items-center gap-3">
                        จัดการข้อมูลลูกค้า
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">สมาชิกทั้งหมด <span class="font-bold text-primary"><?= number_format($stat_total) ?></span> คน</p>
                </div>
                <button class="bg-primary hover:bg-pink-600 text-white px-6 py-2.5 rounded-full font-bold text-sm shadow-lg shadow-primary/30 flex items-center gap-2 transition-transform transform hover:-translate-y-0.5">
                    <span class="material-icons-round text-[18px]">campaign</span> ส่งจดหมายข่าว
                </button>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-3xl shadow-card border border-gray-100 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-yellow-50 flex items-center justify-center text-yellow-500"><span class="material-icons-round">workspace_premium</span></div>
                    <div><p class="text-[11px] text-gray-500 font-bold">สมาชิก Gold</p><h3 class="text-2xl font-extrabold text-gray-800"><?= number_format($stat_gold) ?></h3></div>
                </div>
                <div class="bg-white p-5 rounded-3xl shadow-card border border-gray-100 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-500"><span class="material-icons-round">military_tech</span></div>
                    <div><p class="text-[11px] text-gray-500 font-bold">สมาชิก Silver</p><h3 class="text-2xl font-extrabold text-gray-800"><?= number_format($stat_silver) ?></h3></div>
                </div>
                <div class="bg-white p-5 rounded-3xl shadow-card border border-gray-100 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-orange-50 flex items-center justify-center text-orange-600"><span class="material-icons-round">stars</span></div>
                    <div><p class="text-[11px] text-gray-500 font-bold">สมาชิก Bronze</p><h3 class="text-2xl font-extrabold text-gray-800"><?= number_format($stat_bronze) ?></h3></div>
                </div>
                <div class="bg-white p-5 rounded-3xl shadow-card border border-pink-50 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-pink-50 flex items-center justify-center text-primary"><span class="material-icons-round">person_add</span></div>
                    <div><p class="text-[11px] text-gray-500 font-bold">สมัครใหม่ (เดือนนี้)</p><h3 class="text-2xl font-extrabold text-primary">+<?= number_format($stat_new) ?></h3></div>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] shadow-card overflow-hidden border border-pink-50 relative mt-4">
                <div class="overflow-x-auto min-h-[400px]">
                    <table class="w-full text-left border-collapse min-w-[1000px]">
                        <thead>
                            <tr class="bg-gray-50/80 text-gray-500 text-[12px] uppercase tracking-wider border-b border-gray-100">
                                <th class="px-6 py-4 font-bold pl-8">ชื่อลูกค้า</th>
                                <th class="px-6 py-4 font-bold">อีเมล / เบอร์โทร</th>
                                <th class="px-6 py-4 font-bold">ระดับสมาชิก</th>
                                <th class="px-6 py-4 font-bold">ยอดซื้อสะสม</th>
                                <th class="px-6 py-4 font-bold">วันที่สมัคร</th>
                                <th class="px-6 py-4 font-bold text-center">สถานะ</th>
                                <th class="px-6 py-4 font-bold text-right pr-8">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-50">
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-16 text-center text-gray-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <span class="material-icons-round text-6xl text-gray-200 mb-3">group_off</span>
                                            <p class="text-base font-medium">ยังไม่มีข้อมูลลูกค้าในระบบ</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($customers as $c): 
                                    $imgUrl = (!empty($c['u_image']) && file_exists("../uploads/" . $c['u_image'])) ? "../uploads/" . $c['u_image'] : "https://ui-avatars.com/api/?name=" . urlencode($c['u_username']) . "&background=fce7f3&color=ec2d88";
                                    $dateStr = date('d M Y', strtotime($c['created_at']));
                                    
                                    // กำหนดป้าย Tier
                                    if ($c['tier'] == 'Gold') { $t_bg='bg-yellow-100 text-yellow-600'; $t_ic='workspace_premium'; }
                                    elseif ($c['tier'] == 'Silver') { $t_bg='bg-gray-100 text-gray-600'; $t_ic='military_tech'; }
                                    else { $t_bg='bg-orange-100 text-orange-700'; $t_ic='stars'; }

                                    $c_json = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="hover:bg-pink-50/30 transition-colors group">
                                    <td class="px-6 py-4 pl-8">
                                        <div class="flex items-center gap-3">
                                            <img src="<?= $imgUrl ?>" class="w-10 h-10 rounded-full object-cover shadow-sm border border-gray-100">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-900"><?= htmlspecialchars($c['u_name'] ?? $c['u_username']) ?></span>
                                                <span class="text-[10px] text-gray-400">ID: CUS-<?= str_pad($c['u_id'], 4, '0', STR_PAD_LEFT) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-gray-600 font-medium"><?= htmlspecialchars($c['u_email'] ?? '-') ?></span>
                                            <span class="text-[11px] text-gray-400"><?= htmlspecialchars($c['u_phone'] ?? 'ไม่ระบุเบอร์โทร') ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[11px] font-bold <?= $t_bg ?>">
                                            <span class="material-icons-round text-[14px]"><?= $t_ic ?></span> <?= $c['tier'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-gray-900 text-base">
                                        ฿<?= number_format($c['total_spent'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 font-medium text-xs">
                                        <?= $dateStr ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold text-green-600 bg-green-50 border border-green-100">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> ปกติ
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right pr-8">
                                        <button onclick="openCustomerModal('<?= $c_json ?>')" class="w-8 h-8 rounded-full text-gray-400 hover:text-blue-500 hover:bg-blue-50 transition-colors inline-flex items-center justify-center mr-1" title="ดูรายละเอียด">
                                            <span class="material-icons-round text-[18px]">visibility</span>
                                        </button>
                                        <button onclick="confirmDelete(<?= $c['u_id'] ?>)" class="w-8 h-8 rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors inline-flex items-center justify-center" title="ลบลูกค้า">
                                            <span class="material-icons-round text-[18px]">delete</span>
                                        </button>
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

<div id="customerModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden items-center justify-center opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white rounded-[2rem] w-full max-w-5xl h-auto max-h-[95vh] shadow-2xl flex flex-col overflow-hidden modal-content transform scale-95 transition-transform duration-300 border border-pink-50 relative">
        
        <div class="px-8 py-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <div class="flex items-center gap-2 text-sm text-gray-500 font-medium">
                <span class="hover:text-primary cursor-pointer" onclick="closeCustomerModal()">หน้าแรก</span> 
                <span class="material-icons-round text-[14px]">chevron_right</span> 
                <span class="hover:text-primary cursor-pointer" onclick="closeCustomerModal()">ลูกค้า</span> 
                <span class="material-icons-round text-[14px]">chevron_right</span> 
                <span class="text-primary font-bold">รายละเอียดลูกค้า</span>
            </div>
            <button type="button" onclick="closeCustomerModal()" class="w-8 h-8 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 shadow-sm transition-colors">
                <span class="material-icons-round text-[18px]">close</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto custom-scroll p-8 bg-gray-50/30">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                
                <div class="lg:col-span-4 space-y-6">
                    <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100 text-center relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-b from-pink-50 to-white"></div>
                        <div class="relative z-10">
                            <img id="md_image" src="" class="w-24 h-24 rounded-full object-cover mx-auto shadow-md border-4 border-white mb-4">
                            <h2 class="text-xl font-bold text-gray-900 mb-1" id="md_name">ชื่อลูกค้า</h2>
                            <div class="flex items-center justify-center gap-1.5 mb-2">
                                <span id="md_tier_badge" class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-bold">
                                    <span class="material-icons-round text-[14px]" id="md_tier_icon">stars</span> <span id="md_tier_text">Tier</span>
                                </span>
                                <span class="text-xs text-gray-400">เข้าร่วมเมื่อ <span id="md_join_date"></span></span>
                            </div>
                            
                            <div class="flex justify-between items-center mt-6 pt-6 border-t border-gray-100">
                                <div class="text-left">
                                    <p class="text-xs text-gray-500 mb-1">ยอดรวมทั้งหมด</p>
                                    <p class="text-lg font-extrabold text-gray-800" id="md_total_orders">0</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500 mb-1">ยอดซื้อสะสม</p>
                                    <p class="text-lg font-extrabold text-primary" id="md_total_spent">฿0.00</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-gray-800 flex items-center gap-2"><span class="material-icons-round text-primary text-[18px]">person</span> ข้อมูลส่วนตัว</h3>
                            <button class="text-xs text-primary font-bold hover:underline">แก้ไข</button>
                        </div>
                        <div class="space-y-4">
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-pink-50 flex items-center justify-center text-primary flex-shrink-0"><span class="material-icons-round text-[16px]">phone</span></div>
                                <div><p class="text-[10px] text-gray-400">เบอร์โทรศัพท์</p><p class="text-sm font-medium text-gray-700" id="md_phone">-</p></div>
                            </div>
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-pink-50 flex items-center justify-center text-primary flex-shrink-0"><span class="material-icons-round text-[16px]">email</span></div>
                                <div><p class="text-[10px] text-gray-400">อีเมล</p><p class="text-sm font-medium text-gray-700 break-all" id="md_email">-</p></div>
                            </div>
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-pink-50 flex items-center justify-center text-primary flex-shrink-0"><span class="material-icons-round text-[16px]">location_on</span></div>
                                <div><p class="text-[10px] text-gray-400">ที่อยู่หลัก</p><p class="text-sm font-medium text-gray-700 leading-relaxed" id="md_address">-</p></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-8 space-y-6">
                    
                    <div class="grid grid-cols-3 gap-4">
                        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs font-bold text-gray-500">ออเดอร์ทั้งหมด</span>
                                <span class="material-icons-round text-gray-300 text-[18px]">receipt_long</span>
                            </div>
                            <span class="text-2xl font-bold text-gray-800" id="md_stat_ord_count">0</span> <span class="text-xs text-gray-400">ครั้ง</span>
                        </div>
                        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs font-bold text-gray-500">ยอดเฉลี่ยต่อบิล</span>
                                <span class="material-icons-round text-gray-300 text-[18px]">payments</span>
                            </div>
                            <span class="text-2xl font-bold text-gray-800" id="md_stat_avg">฿0</span>
                        </div>
                        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs font-bold text-gray-500">สั่งซื้อล่าสุด</span>
                                <span class="material-icons-round text-gray-300 text-[18px]">schedule</span>
                            </div>
                            <span class="text-sm font-bold text-gray-800 mt-1 block" id="md_stat_last_date">-</span>
                        </div>
                    </div>

                    <div class="bg-gradient-to-r from-primary to-purple-500 rounded-2xl p-6 text-white flex justify-between items-center shadow-lg shadow-primary/20">
                        <div>
                            <h3 class="font-bold text-lg">จัดการแต้มสะสม</h3>
                            <p class="text-xs opacity-80 mt-1">ปรับปรุงคะแนนสะสมสำหรับลูกค้ารายนี้</p>
                        </div>
                        <div class="flex items-center bg-black/20 rounded-full p-1 backdrop-blur-sm">
                            <button class="w-8 h-8 flex items-center justify-center hover:bg-white/20 rounded-full transition"><span class="material-icons-round text-sm">remove</span></button>
                            <span class="px-4 font-bold font-mono">1,240</span>
                            <button class="w-8 h-8 flex items-center justify-center hover:bg-white/20 rounded-full transition"><span class="material-icons-round text-sm">add</span></button>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-50 pb-4">
                            <h3 class="font-bold text-gray-800 flex items-center gap-2"><span class="material-icons-round text-primary text-[18px]">history</span> ประวัติสั่งซื้อ (ล่าสุด)</h3>
                            <a href="#" class="text-xs text-primary font-bold hover:underline">ดูประวัติทั้งหมด</a>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-gray-400 text-[11px] uppercase tracking-wider">
                                        <th class="pb-3 font-medium">หมายเลขคำสั่งซื้อ</th>
                                        <th class="pb-3 font-medium">วันที่</th>
                                        <th class="pb-3 font-medium">สถานะ</th>
                                        <th class="pb-3 font-medium text-right">ยอดรวม</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm" id="md_orders_body">
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // 🟢 แสดงข้อมูลใน Modal 🟢
    function openCustomerModal(jsonData) {
        const cus = JSON.parse(jsonData);
        
        // รูปโปรไฟล์
        let imgUrl = "https://ui-avatars.com/api/?name=" + encodeURIComponent(cus.u_username) + "&background=fce7f3&color=ec2d88";
        if (cus.u_image) imgUrl = '../uploads/' + cus.u_image; // ปรับ Path ตามจริง
        document.getElementById('md_image').src = imgUrl;
        
        document.getElementById('md_name').innerText = cus.u_name || cus.u_username;
        document.getElementById('md_email').innerText = cus.u_email || '-';
        document.getElementById('md_phone').innerText = cus.u_phone || '-';
        document.getElementById('md_address').innerText = cus.u_address || 'ไม่มีข้อมูลที่อยู่';
        
        // แปลงวันที่
        const dateObj = new Date(cus.created_at);
        document.getElementById('md_join_date').innerText = dateObj.toLocaleDateString('th-TH', { year: 'numeric', month: 'short', day: 'numeric' });

        // Tier
        const tierBadge = document.getElementById('md_tier_badge');
        const tierIcon = document.getElementById('md_tier_icon');
        document.getElementById('md_tier_text').innerText = cus.tier;
        
        if (cus.tier === 'Gold') {
            tierBadge.className = 'inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-bold bg-yellow-100 text-yellow-600';
            tierIcon.innerText = 'workspace_premium';
        } else if (cus.tier === 'Silver') {
            tierBadge.className = 'inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600';
            tierIcon.innerText = 'military_tech';
        } else {
            tierBadge.className = 'inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-bold bg-orange-100 text-orange-700';
            tierIcon.innerText = 'stars';
        }

        // Stats
        const totalSpent = parseFloat(cus.total_spent);
        const totalOrders = parseInt(cus.total_orders);
        document.getElementById('md_total_orders').innerText = totalOrders;
        document.getElementById('md_total_spent').innerText = '฿' + totalSpent.toLocaleString('th-TH', {minimumFractionDigits: 2});
        
        document.getElementById('md_stat_ord_count').innerText = totalOrders;
        let avg = totalOrders > 0 ? (totalSpent / totalOrders) : 0;
        document.getElementById('md_stat_avg').innerText = '฿' + avg.toLocaleString('th-TH', {maximumFractionDigits: 0});

        // ประวัติออเดอร์
        const tbody = document.getElementById('md_orders_body');
        tbody.innerHTML = '';
        if (cus.recent_orders && cus.recent_orders.length > 0) {
            // โชว์วันที่ล่าสุด
            const lastDateObj = new Date(cus.recent_orders[0].created_at);
            document.getElementById('md_stat_last_date').innerText = lastDateObj.toLocaleDateString('th-TH', { year: 'numeric', month: 'short', day: 'numeric' });

            cus.recent_orders.forEach(ord => {
                let badgeClass = 'bg-gray-100 text-gray-600';
                let badgeText = ord.status;
                if(ord.status === 'pending') { badgeClass = 'bg-orange-100 text-orange-600'; badgeText = 'รอชำระเงิน'; }
                else if(ord.status === 'processing') { badgeClass = 'bg-blue-100 text-blue-600'; badgeText = 'กำลังจัดส่ง'; }
                else if(ord.status === 'shipped') { badgeClass = 'bg-purple-100 text-purple-600'; badgeText = 'จัดส่งแล้ว'; }
                else if(ord.status === 'completed') { badgeClass = 'bg-green-100 text-green-600'; badgeText = 'สำเร็จแล้ว'; }
                else if(ord.status === 'cancelled') { badgeClass = 'bg-red-100 text-red-600'; badgeText = 'ยกเลิก'; }

                const ordDate = new Date(ord.created_at).toLocaleDateString('th-TH', { year: 'numeric', month: 'short', day: 'numeric' });
                
                tbody.innerHTML += `
                    <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition">
                        <td class="py-3 font-bold text-primary text-xs">#${ord.order_no}</td>
                        <td class="py-3 text-gray-500 text-xs">${ordDate}</td>
                        <td class="py-3">
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold ${badgeClass}">${badgeText}</span>
                        </td>
                        <td class="py-3 font-bold text-gray-800 text-right text-xs">฿${parseFloat(ord.total_amount).toLocaleString('th-TH')}</td>
                    </tr>
                `;
            });
        } else {
            document.getElementById('md_stat_last_date').innerText = '-';
            tbody.innerHTML = `<tr><td colspan="4" class="py-6 text-center text-gray-400 text-xs">ไม่มีประวัติการสั่งซื้อ</td></tr>`;
        }

        // เปิด Modal
        const modal = document.getElementById('customerModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('.modal-content').classList.remove('scale-95');
        }, 10);
    }

    function closeCustomerModal() {
        const modal = document.getElementById('customerModal');
        modal.classList.add('opacity-0');
        modal.querySelector('.modal-content').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }

    // 🟢 ระบบลบด้วย SweetAlert 🟢
    function confirmDelete(id) {
        Swal.fire({
            title: 'ยืนยันการลบลูกค้า?',
            text: "ข้อมูลผู้ใช้และประวัติที่เกี่ยวข้องจะถูกลบถาวร ไม่สามารถกู้คืนได้!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#9CA3AF',
            confirmButtonText: 'ใช่, ลบข้อมูลเลย!',
            cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-full px-6', cancelButton: 'rounded-full px-6' }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?delete_id=' + id;
            }
        });
    }

    // แจ้งเตือนเมื่อลบสำเร็จ
    <?php if (isset($_SESSION['success_msg'])): ?>
        Swal.fire({ 
            toast: true, position: 'top-end', icon: 'success', 
            title: '<?= $_SESSION['success_msg'] ?>', 
            showConfirmButton: false, timer: 3000,
            customClass: { popup: 'rounded-2xl' }
        });
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
</script>
</body>
</html>