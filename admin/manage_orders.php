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
    // ลบรายการย่อย และออเดอร์หลัก (เอา payment ออกเพราะใน DB ไม่มี order_id ในตาราง payment)
    mysqli_query($conn, "DELETE FROM `order_items` WHERE order_id = $del_id");
    mysqli_query($conn, "DELETE FROM `orders` WHERE order_id = $del_id");
    
    $_SESSION['success_msg'] = "ลบรายการสั่งซื้อเรียบร้อยแล้ว!";
    header("Location: manage_orders.php");
    exit();
}

// ==========================================
// 4. ตัวกรอง (Filter) สถานะ
// ==========================================
$current_filter = $_GET['status'] ?? 'all';
$whereClause = "";
if ($current_filter !== 'all') {
    $safe_filter = mysqli_real_escape_string($conn, $current_filter);
    $whereClause = "WHERE o.status = '$safe_filter'";
}

// ==========================================
// 5. ดึงข้อมูลออเดอร์ทั้งหมด (แก้ไข SQL ให้ตรงกับ DB)
// ==========================================
$orders = [];
$sqlOrders = "
    SELECT o.*, a.u_username, a.u_name, u.u_phone, 
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
        
        // ดึงไอเทมของแต่ละออเดอร์
        $items = [];
        $sqlItems = "SELECT * FROM `order_items` WHERE order_id = $o_id";
        $resItems = mysqli_query($conn, $sqlItems);
        while ($item = mysqli_fetch_assoc($resItems)) {
            // ดึงรูปสินค้าจากตาราง product มาด้วย
            $p_id = $item['p_id'];
            $imgRes = mysqli_query($conn, "SELECT p_image FROM `product` WHERE p_id = $p_id");
            if ($imgRow = mysqli_fetch_assoc($imgRes)) {
                $item['p_image'] = $imgRow['p_image'];
            } else {
                $item['p_image'] = '';
            }
            $items[] = $item;
        }
        
        $row['items'] = $items;
        // Mock ข้อมูลที่ยังไม่มีในฐานข้อมูล
        $row['payment_method'] = 'ไม่ระบุ'; 
        $row['slip_image'] = null; 
        
        $orders[] = $row;
    }
}

// นับจำนวนตามสถานะสำหรับ Tab
$countAll = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders`"))['c'] ?? 0;
$countPending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders` WHERE status='pending'"))['c'] ?? 0;
$countProcessing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders` WHERE status='processing'"))['c'] ?? 0;
$countShipped = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders` WHERE status='shipped'"))['c'] ?? 0;
$countCompleted = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders` WHERE status='completed'"))['c'] ?? 0;

// ฟังก์ชันแปลงสถานะเป็น Badge สวยๆ
function getStatusBadge($status) {
    $badges = [
        'pending' => ['text' => 'รอชำระเงิน', 'class' => 'bg-orange-100 text-orange-600 border-orange-200', 'icon' => 'schedule'],
        'processing' => ['text' => 'กำลังจัดส่ง', 'class' => 'bg-blue-100 text-blue-600 border-blue-200', 'icon' => 'inventory_2'],
        'shipped' => ['text' => 'จัดส่งแล้ว', 'class' => 'bg-purple-100 text-purple-600 border-purple-200', 'icon' => 'local_shipping'],
        'completed' => ['text' => 'สำเร็จแล้ว', 'class' => 'bg-green-100 text-green-600 border-green-200', 'icon' => 'check_circle'],
        'cancelled' => ['text' => 'ยกเลิก', 'class' => 'bg-red-100 text-red-600 border-red-200', 'icon' => 'cancel']
    ];
    return $badges[$status] ?? ['text' => 'ไม่ทราบ', 'class' => 'bg-gray-100 text-gray-600 border-gray-200', 'icon' => 'help_outline'];
}

// ฟังก์ชันแปลงวิธีชำระเงิน
function getPaymentMethodText($method) {
    if ($method == 'promptpay' || $method == 'โอนเงิน') return ['text' => 'โอนเงิน', 'icon' => 'account_balance', 'color' => 'text-blue-500'];
    if ($method == 'credit_card') return ['text' => 'บัตรเครดิต', 'icon' => 'credit_card', 'color' => 'text-purple-500'];
    if ($method == 'cod') return ['text' => 'COD ปลายทาง', 'icon' => 'local_shipping', 'color' => 'text-orange-500'];
    return ['text' => 'ไม่ระบุ', 'icon' => 'payments', 'color' => 'text-gray-400'];
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
                <a class="nav-item-active flex items-center gap-4 px-5 py-3.5 rounded-2xl transition-all duration-300 group" href="manage_orders.php">
                    <span class="material-icons-round">receipt_long</span>
                    <span class="font-bold text-[15px]">รายการสั่งซื้อ</span>
                    <?php if($countPending > 0): ?>
                        <span class="ml-auto bg-white text-primary text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm"><?= $countPending ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="manage_customers.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">group</span>
                    <span class="font-medium text-[15px]">ข้อมูลลูกค้า</span>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="settings.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">settings</span>
                    <span class="font-medium text-[15px]">ตั้งค่าระบบ</span>
                </a>
            </nav>
        </div>
        <div class="p-5 rounded-2xl bg-gradient-to-br from-pink-50 to-purple-50 border border-pink-100 flex items-center gap-3 shadow-sm">
            <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-primary shadow-sm">
                <span class="material-icons-round text-xl">support_agent</span>
            </div>
            <div>
                <p class="text-sm font-bold text-primary">ต้องการความช่วยเหลือ?</p>
                <p class="text-xs text-text-muted">ติดต่อทีมพัฒนาระบบ</p>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0">
        <header class="flex items-center justify-between px-6 py-4 lg:px-10 lg:py-5 glass-panel sticky top-0 z-10">
            <div class="flex items-center gap-4 lg:hidden">
                <button class="p-2 text-text-main hover:bg-pink-50 rounded-xl transition-colors">
                    <span class="material-icons-round">menu</span>
                </button>
                <span class="font-bold text-xl text-primary flex items-center gap-1"><span class="material-icons-round">spa</span> Lumina</span>
            </div>
            
            <div class="hidden md:flex flex-1 max-w-md relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <span class="material-icons-round text-gray-400 group-focus-within:text-primary transition-colors text-[20px]">search</span>
                </div>
                <input class="block w-full pl-12 pr-4 py-2.5 rounded-full border border-pink-100 bg-white shadow-sm text-sm placeholder-gray-400 focus:ring-2 focus:ring-primary/20 transition-all outline-none" placeholder="ค้นหาหมายเลขคำสั่งซื้อ, ชื่อลูกค้า..." type="text"/>
            </div>
            
            <div class="flex items-center gap-4 lg:gap-6 ml-auto">
                <button class="relative p-2.5 rounded-full bg-white text-gray-500 hover:text-primary hover:bg-pink-50 transition-colors shadow-sm border border-pink-50">
                    <span class="material-icons-round text-[22px]">notifications</span>
                    <?= $countPending > 0 ? '<span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white"></span>' : '' ?>
                </button>
                
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
                                    <div class="bg-white rounded-full p-[2px] w-16 h-16">
                                        <img src="<?= $adminAvatar ?>" alt="Profile" class="w-full h-full rounded-full object-cover">
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mb-6">
                                <h3 class="text-[20px] font-bold text-gray-800">สวัสดี, คุณ <?= htmlspecialchars($adminName) ?></h3>
                            </div>
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
            
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-800 tracking-tight flex items-center gap-3">
                        จัดการรายการสั่งซื้อ 
                        <span class="text-sm font-medium bg-pink-100 text-primary px-3 py-1 rounded-full">ทั้งหมด <?= $countAll ?> รายการ</span>
                    </h1>
                </div>
            </div>

            <div class="bg-white rounded-full p-1.5 shadow-sm border border-gray-100 inline-flex overflow-x-auto custom-scroll w-full sm:w-auto">
                <a href="?status=all" class="px-5 py-2 rounded-full text-sm font-bold whitespace-nowrap transition-all <?= $current_filter == 'all' ? 'bg-primary text-white shadow-sm' : 'text-gray-500 hover:bg-pink-50 hover:text-primary' ?>">
                    ทั้งหมด
                </a>
                <a href="?status=pending" class="px-5 py-2 rounded-full text-sm font-bold whitespace-nowrap transition-all flex items-center gap-2 <?= $current_filter == 'pending' ? 'bg-primary text-white shadow-sm' : 'text-gray-500 hover:bg-pink-50 hover:text-primary' ?>">
                    รอชำระเงิน <?= $countPending > 0 ? '<span class="w-5 h-5 flex items-center justify-center bg-red-500 text-white text-[10px] rounded-full">'.$countPending.'</span>' : '' ?>
                </a>
                <a href="?status=processing" class="px-5 py-2 rounded-full text-sm font-bold whitespace-nowrap transition-all <?= $current_filter == 'processing' ? 'bg-primary text-white shadow-sm' : 'text-gray-500 hover:bg-pink-50 hover:text-primary' ?>">
                    กำลังจัดส่ง (<?= $countProcessing ?>)
                </a>
                <a href="?status=shipped" class="px-5 py-2 rounded-full text-sm font-bold whitespace-nowrap transition-all <?= $current_filter == 'shipped' ? 'bg-primary text-white shadow-sm' : 'text-gray-500 hover:bg-pink-50 hover:text-primary' ?>">
                    จัดส่งแล้ว (<?= $countShipped ?>)
                </a>
                <a href="?status=completed" class="px-5 py-2 rounded-full text-sm font-bold whitespace-nowrap transition-all <?= $current_filter == 'completed' ? 'bg-primary text-white shadow-sm' : 'text-gray-500 hover:bg-pink-50 hover:text-primary' ?>">
                    สำเร็จแล้ว (<?= $countCompleted ?>)
                </a>
            </div>

            <div class="bg-white rounded-[2rem] shadow-card overflow-hidden border border-pink-50 relative">
                <div class="overflow-x-auto min-h-[400px]">
                    <table class="w-full text-left border-collapse min-w-[1000px]">
                        <thead>
                            <tr class="bg-gray-50/80 text-gray-500 text-[13px] uppercase tracking-wider border-b border-gray-100">
                                <th class="px-6 py-5 font-bold pl-8">หมายเลขคำสั่งซื้อ</th>
                                <th class="px-6 py-5 font-bold">ชื่อลูกค้า</th>
                                <th class="px-6 py-5 font-bold">วันที่สั่งซื้อ</th>
                                <th class="px-6 py-5 font-bold">ยอดรวมสุทธิ</th>
                                <th class="px-6 py-5 font-bold">ช่องทางการชำระเงิน</th>
                                <th class="px-6 py-5 font-bold text-center">สถานะ</th>
                                <th class="px-6 py-5 font-bold text-right pr-8">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-50">
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-16 text-center text-gray-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <span class="material-icons-round text-6xl text-gray-200 mb-3">inbox</span>
                                            <p class="text-base font-medium">ไม่พบรายการสั่งซื้อในสถานะนี้</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($orders as $o): 
                                    $badge = getStatusBadge($o['status']);
                                    $payInfo = getPaymentMethodText($o['payment_method']);
                                    $dateStr = date('d M Y, H:i', strtotime($o['created_at']));
                                    
                                    // แปลงข้อมูลไอเทมและข้อมูลลูกค้าเป็น JSON เพื่อง่ายต่อการส่งเข้า Modal
                                    $o_json = htmlspecialchars(json_encode($o), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="hover:bg-pink-50/30 transition-colors group">
                                    <td class="px-6 py-4 pl-8">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-gray-900 text-base">#<?= htmlspecialchars($o['order_no']) ?></span>
                                            <span class="text-[11px] text-gray-400"><?= count($o['items']) ?> รายการ</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-pink-100 text-primary flex items-center justify-center font-bold text-sm">
                                                <?= mb_substr($o['u_name'] ?? $o['u_username'] ?? 'U', 0, 1, 'UTF-8') ?>
                                            </div>
                                            <span class="font-bold text-gray-700"><?= htmlspecialchars($o['u_name'] ?? $o['u_username'] ?? 'ลูกค้าทั่วไป') ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 font-medium">
                                        <?= $dateStr ?>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-gray-900 text-base">
                                        ฿<?= number_format($o['total_amount'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2 <?= $payInfo['color'] ?> bg-gray-50 w-fit px-3 py-1 rounded-lg border border-gray-100">
                                            <span class="material-icons-round text-[16px]"><?= $payInfo['icon'] ?></span>
                                            <span class="font-bold text-xs"><?= $payInfo['text'] ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold border <?= $badge['class'] ?>">
                                            <span class="material-icons-round text-[14px]"><?= $badge['icon'] ?></span> <?= $badge['text'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right pr-8">
                                        <button onclick="openOrderModal('<?= $o_json ?>')" class="w-9 h-9 rounded-full text-gray-400 hover:text-blue-500 hover:bg-blue-50 transition-colors inline-flex items-center justify-center mr-1" title="ดูรายละเอียด">
                                            <span class="material-icons-round text-[20px]">visibility</span>
                                        </button>
                                        <button onclick="confirmDelete(<?= $o['order_id'] ?>)" class="w-9 h-9 rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors inline-flex items-center justify-center" title="ลบรายการ">
                                            <span class="material-icons-round text-[20px]">delete</span>
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

<div id="orderModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden items-center justify-center opacity-0 transition-opacity duration-300 p-4">
    <div class="bg-white rounded-[2rem] w-full max-w-5xl h-auto max-h-[95vh] shadow-2xl flex flex-col overflow-hidden modal-content transform scale-95 transition-transform duration-300 border border-pink-50 relative">
        
        <div class="px-8 py-5 border-b border-pink-50 flex justify-between items-center bg-gradient-to-r from-pink-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-primary shadow-sm border border-pink-100">
                    <span class="material-icons-round text-2xl">local_mall</span>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        คำสั่งซื้อ <span id="md_order_no" class="text-primary">#ORD-XXXX</span>
                    </h2>
                    <p class="text-xs text-gray-500 flex items-center gap-1 mt-0.5">
                        <span class="material-icons-round text-[14px]">schedule</span> <span id="md_date">XX X.X. XXXX, XX:XX น.</span>
                    </p>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <form action="" method="POST" class="flex items-center gap-2 m-0 bg-white p-1 rounded-full border border-gray-200 shadow-sm">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" id="md_form_order_id" value="">
                    <select name="status" id="md_status_select" class="text-sm font-bold text-gray-700 bg-transparent border-none focus:ring-0 cursor-pointer py-1 pl-3 pr-8">
                        <option value="pending">รอชำระเงิน</option>
                        <option value="processing">กำลังจัดส่ง</option>
                        <option value="shipped">จัดส่งแล้ว</option>
                        <option value="completed">สำเร็จแล้ว</option>
                        <option value="cancelled">ยกเลิก</option>
                    </select>
                    <button type="submit" class="bg-primary hover:bg-pink-600 text-white px-4 py-1.5 rounded-full text-xs font-bold transition-colors">อัปเดต</button>
                </form>

                <button type="button" onclick="closeOrderModal()" class="w-10 h-10 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 shadow-sm transition-colors">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto custom-scroll p-8 bg-gray-50/30">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <div class="space-y-6">
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2"><span class="material-icons-round text-primary">shopping_bag</span> รายการสินค้า</h3>
                        <div id="md_items_container" class="space-y-4 max-h-[300px] overflow-y-auto pr-2 custom-scroll">
                            </div>
                    </div>

                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2"><span class="material-icons-round text-primary">payments</span> หลักฐานการชำระเงิน</h3>
                        <div class="flex gap-6 items-start">
                            <div class="w-32 h-40 bg-gray-100 rounded-xl overflow-hidden border border-gray-200 flex items-center justify-center flex-shrink-0 cursor-pointer hover:opacity-80 transition" onclick="window.open(document.getElementById('md_slip_img').src, '_blank')">
                                <img id="md_slip_img" src="" alt="Slip" class="w-full h-full object-cover hidden">
                                <span id="md_slip_none" class="text-xs text-gray-400 font-medium">ไม่มีรูปสลิป</span>
                            </div>
                            <div class="space-y-3 flex-1">
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">ช่องทางชำระเงิน:</p>
                                    <div class="font-bold text-sm bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg inline-block border border-blue-100" id="md_pay_method">โอนผ่านธนาคาร</div>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 mb-0.5">ยอดชำระ:</p>
                                    <p class="font-bold text-primary text-lg" id="md_pay_amount">฿0.00</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 relative overflow-hidden">
                        <div class="absolute top-0 right-0 p-3 opacity-5"><span class="material-icons-round text-6xl text-primary">person</span></div>
                        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2 relative z-10"><span class="material-icons-round text-primary">person</span> ข้อมูลลูกค้า</h3>
                        
                        <div class="space-y-4 relative z-10">
                            <div>
                                <p class="text-xs text-gray-500 mb-0.5">ชื่อลูกค้า</p>
                                <p class="font-bold text-gray-800 text-sm" id="md_cus_name">คุณ มะลิ สวยใส</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-0.5">เบอร์โทรศัพท์</p>
                                <p class="font-bold text-gray-800 text-sm flex items-center gap-1"><span class="material-icons-round text-[14px] text-gray-400">phone</span> <span id="md_cus_phone">081-234-5678</span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-0.5">ที่อยู่จัดส่ง</p>
                                <p class="font-medium text-gray-600 text-sm leading-relaxed bg-gray-50 p-3 rounded-xl border border-gray-100" id="md_cus_address">123/45 ซอย...</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-pink-100 bg-gradient-to-b from-white to-pink-50/30">
                        <h3 class="font-bold text-gray-800 mb-4">สรุปยอดชำระ</h3>
                        <div class="space-y-3 pb-4 border-b border-dashed border-gray-200">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">ยอดรวมสินค้า</span>
                                <span class="font-bold text-gray-700" id="md_sum_items">฿0.00</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">ค่าจัดส่ง</span>
                                <span class="font-bold text-gray-700" id="md_sum_shipping">฿0.00</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-end pt-4">
                            <span class="font-bold text-gray-800">ยอดสุทธิ</span>
                            <span class="font-extrabold text-2xl text-primary tracking-tight" id="md_sum_total">฿0.00</span>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" class="flex-1 bg-white border-2 border-gray-200 text-gray-600 font-bold py-3 rounded-xl hover:bg-gray-50 transition-colors flex items-center justify-center gap-2">
                            <span class="material-icons-round text-[18px]">print</span> พิมพ์ใบเสร็จ
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    function openOrderModal(jsonData) {
        const order = JSON.parse(jsonData);
        
        document.getElementById('md_order_no').innerText = '#' + order.order_no;
        
        const dateObj = new Date(order.created_at);
        const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        document.getElementById('md_date').innerText = dateObj.toLocaleDateString('th-TH', options) + ' น.';
        
        document.getElementById('md_form_order_id').value = order.order_id;
        document.getElementById('md_status_select').value = order.status;

        document.getElementById('md_cus_name').innerText = order.u_name || order.u_username;
        document.getElementById('md_cus_phone').innerText = order.u_phone || 'ไม่ระบุ';
        document.getElementById('md_cus_address').innerText = order.u_address || 'ไม่ระบุ';

        let payMethodText = 'ไม่ระบุ';
        if(order.payment_method === 'promptpay') payMethodText = 'โอนเงินผ่านธนาคาร (QR)';
        else if(order.payment_method === 'credit_card') payMethodText = 'บัตรเครดิต/เดบิต';
        else if(order.payment_method === 'cod') payMethodText = 'ชำระเงินปลายทาง (COD)';
        document.getElementById('md_pay_method').innerText = payMethodText;
        
        document.getElementById('md_pay_amount').innerText = '฿' + parseFloat(order.total_amount).toLocaleString('th-TH', {minimumFractionDigits: 2});
        
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

        const itemsContainer = document.getElementById('md_items_container');
        itemsContainer.innerHTML = '';
        let itemsTotal = 0;
        
        order.items.forEach(item => {
            itemsTotal += parseFloat(item.p_price) * parseInt(item.quantity);
            const imgSrc = item.p_image ? '../uploads/products/' + item.p_image : 'https://via.placeholder.com/80';
            
            itemsContainer.innerHTML += `
                <div class="flex gap-4 items-center border-b border-gray-50 pb-3 last:border-0 last:pb-0">
                    <img src="${imgSrc}" class="w-16 h-16 rounded-xl object-cover border border-gray-100 shadow-sm">
                    <div class="flex-1">
                        <p class="font-bold text-gray-800 text-sm line-clamp-1">${item.p_name}</p>
                        <p class="text-xs text-gray-400 mt-0.5">ราคาต่อชิ้น: ฿${parseFloat(item.p_price).toLocaleString('th-TH')}</p>
                        <p class="text-xs text-primary font-bold mt-0.5">จำนวน: ${item.quantity}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-gray-800 text-sm">฿${(item.p_price * item.quantity).toLocaleString('th-TH')}</p>
                    </div>
                </div>
            `;
        });

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
            text: "ข้อมูลรายการสั่งซื้อและการชำระเงินจะถูกลบถาวร!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#9CA3AF',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-full px-6', cancelButton: 'rounded-full px-6' }
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
            customClass: { popup: 'rounded-2xl' }
        });
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
</script>
</body>
</html>