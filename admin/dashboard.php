<?php
session_start();
require_once '../config/connectdbuser.php'; // ปรับ path ให้ตรงกับไฟล์เชื่อมต่อ DB ของคุณ

// ตรวจสอบสิทธิ์ Admin (เดี๋ยวคุณค่อยมาทำระบบ Login Admin ทีหลัง ตอนนี้ข้ามไปก่อน)
// if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

$today = date('Y-m-d');

// ==========================================
// 1. ดึงข้อมูลสถิติภาพรวม (Stats)
// ==========================================

// 1.1 ยอดขายวันนี้ (รวมเฉพาะบิลที่จ่ายแล้ว/สำเร็จ)
$sqlSales = "SELECT SUM(total_amount) as today_sales FROM `orders` WHERE DATE(created_at) = '$today' AND status IN ('processing', 'shipped', 'completed')";
$resSales = mysqli_query($conn, $sqlSales);
$todaySales = mysqli_fetch_assoc($resSales)['today_sales'] ?? 0;

// 1.2 คำสั่งซื้อใหม่วันนี้
$sqlNewOrders = "SELECT COUNT(order_id) as new_orders FROM `orders` WHERE DATE(created_at) = '$today'";
$resNewOrders = mysqli_query($conn, $sqlNewOrders);
$newOrders = mysqli_fetch_assoc($resNewOrders)['new_orders'] ?? 0;

// 1.3 สินค้าใกล้หมดสต๊อก (สมมติว่าถ้า p_stock น้อยกว่าหรือเท่ากับ 10 คือใกล้หมด)
$lowStockLimit = 10;
$sqlLowStock = "SELECT COUNT(p_id) as low_stock FROM `product` WHERE p_stock <= $lowStockLimit";
$resLowStock = mysqli_query($conn, $sqlLowStock);
$lowStock = mysqli_fetch_assoc($resLowStock)['low_stock'] ?? 0;

// 1.4 จำนวนสมาชิกรวม (แทนผู้เข้าชมเว็บไซต์)
$sqlUsers = "SELECT COUNT(u_id) as total_users FROM `account`";
$resUsers = mysqli_query($conn, $sqlUsers);
$totalUsers = mysqli_fetch_assoc($resUsers)['total_users'] ?? 0;

// ==========================================
// 2. ข้อมูลกราฟยอดขายย้อนหลัง 7 วัน
// ==========================================
$chartLabels = [];
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $displayDate = date('d M', strtotime("-$i days"));
    
    // ดึงยอดขายแต่ละวัน
    $sqlDaily = "SELECT SUM(total_amount) as daily_sales FROM `orders` WHERE DATE(created_at) = '$date' AND status IN ('processing', 'shipped', 'completed')";
    $resDaily = mysqli_query($conn, $sqlDaily);
    $sales = mysqli_fetch_assoc($resDaily)['daily_sales'] ?? 0;

    $chartLabels[] = $displayDate;
    $chartData[] = (float)$sales;
}

// ==========================================
// 3. ดึงข้อมูลรายการสั่งซื้อล่าสุด 5 รายการ
// ==========================================
$recentOrders = [];
$sqlOrders = "
    SELECT o.order_id, o.order_no, o.total_amount, o.status, o.created_at, a.u_username 
    FROM `orders` o 
    LEFT JOIN `account` a ON o.u_id = a.u_id 
    ORDER BY o.created_at DESC 
    LIMIT 5
";
if ($resOrders = mysqli_query($conn, $sqlOrders)) {
    while ($row = mysqli_fetch_assoc($resOrders)) {
        // ดึงชื่อสินค้าชิ้นแรกในบิลมาแสดง
        $o_id = $row['order_id'];
        $sqlItem = "SELECT p_name FROM `order_items` WHERE order_id = $o_id LIMIT 1";
        $resItem = mysqli_query($conn, $sqlItem);
        $itemName = mysqli_fetch_assoc($resItem)['p_name'] ?? 'ไม่มีข้อมูลสินค้า';

        // นับว่ามีสินค้ากี่ชิ้นในบิล
        $sqlCount = "SELECT COUNT(*) as c FROM `order_items` WHERE order_id = $o_id";
        $itemCount = mysqli_fetch_assoc(mysqli_query($conn, $sqlCount))['c'] ?? 0;
        
        if ($itemCount > 1) {
            $itemName .= " และอื่นๆอีก " . ($itemCount - 1) . " รายการ";
        }
        
        $row['product_summary'] = $itemName;
        $recentOrders[] = $row;
    }
}

// ฟังก์ชันแปลงสถานะ
function getBadge($status) {
    $badges = [
        'pending' => ['text' => 'รอชำระเงิน', 'class' => 'bg-orange-100 text-orange-600', 'dot' => 'bg-orange-500'],
        'processing' => ['text' => 'กำลังเตรียมจัดส่ง', 'class' => 'bg-blue-100 text-blue-600', 'dot' => 'bg-blue-500'],
        'shipped' => ['text' => 'อยู่ระหว่างจัดส่ง', 'class' => 'bg-purple-100 text-purple-600', 'dot' => 'bg-purple-500'],
        'completed' => ['text' => 'สำเร็จแล้ว', 'class' => 'bg-green-100 text-green-600', 'dot' => 'bg-green-500'],
        'cancelled' => ['text' => 'ยกเลิก', 'class' => 'bg-gray-100 text-gray-500', 'dot' => 'bg-gray-500']
    ];
    return $badges[$status] ?? ['text' => 'ไม่ทราบ', 'class' => 'bg-gray-100 text-gray-600', 'dot' => 'bg-gray-500'];
}
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Lumina Beauty Admin Dashboard</title>

<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#F43F85",
                    "primary-light": "#fce7f3",
                    secondary: "#a855f7",
                    "background-light": "#fef6fa",
                    "background-dark": "#1F1B24",
                    "surface-white": "#ffffff",
                    "text-main": "#4a1d35",
                    "text-muted": "#9d7b8c",
                },
                fontFamily: {
                    display: ["Prompt", "sans-serif"],
                    body: ["Prompt", "sans-serif"]
                },
                borderRadius: {
                    DEFAULT: "1rem", "lg": "1.5rem", "xl": "2rem", "2xl": "2.5rem"
                },
                boxShadow: {
                    "soft": "0 10px 40px -10px rgba(244, 63, 133, 0.15)",
                    "card": "0 4px 20px -2px rgba(244, 63, 133, 0.05)"
                }
            },
        },
    }
</script>
<style>
    body { font-family: 'Prompt', sans-serif; }
    .sidebar-gradient { background: linear-gradient(180deg, #fdf4f9 0%, #fefcfd 100%); }
    .glass-panel { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.5); }
    .nav-item-active { background-color: #F43F85; color: white; box-shadow: 0 4px 12px rgba(244, 63, 133, 0.3); }
    .nav-item:hover:not(.nav-item-active) { background-color: #fce7f3; color: #F43F85; }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: #FBCFE8; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #F43F85; }
</style>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-main antialiased overflow-x-hidden">
<div class="flex min-h-screen w-full">
    
    <aside class="hidden lg:flex flex-col w-72 h-screen sticky top-0 border-r border-primary/10 sidebar-gradient p-6 justify-between z-20">
        <div>
            <div class="flex items-center gap-3 px-2 mb-10">
                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary/10 text-primary">
                    <span class="material-icons-round text-3xl">spa</span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-text-main font-display">Lumina Admin</h1>
            </div>
            <nav class="flex flex-col gap-2">
                <a class="nav-item-active flex items-center gap-4 px-5 py-3.5 rounded-2xl transition-all duration-300 group" href="#">
                    <span class="material-icons-round">dashboard</span>
                    <span class="font-bold text-sm">ภาพรวม</span>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="manage_products.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">inventory_2</span>
                    <span class="font-medium text-sm">จัดการสินค้า</span>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="#">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">receipt_long</span>
                    <span class="font-medium text-sm">รายการสั่งซื้อ</span>
                    <?php if($newOrders > 0): ?>
                        <span class="ml-auto bg-primary text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $newOrders ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="#">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">group</span>
                    <span class="font-medium text-sm">ข้อมูลลูกค้า</span>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="#">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">settings</span>
                    <span class="font-medium text-sm">ตั้งค่าระบบ</span>
                </a>
            </nav>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0">
        <header class="flex items-center justify-between px-6 py-4 lg:px-10 lg:py-6 glass-panel sticky top-0 z-10 border-b border-white/50">
            <div class="flex items-center gap-4 lg:hidden">
                <button class="p-2 text-text-main hover:bg-black/5 rounded-xl">
                    <span class="material-icons-round">menu</span>
                </button>
                <span class="font-bold text-lg text-primary">Lumina</span>
            </div>
            
            <div class="hidden md:flex flex-1 max-w-md relative group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="material-icons-round text-text-muted group-focus-within:text-primary transition-colors">search</span>
                </div>
                <input class="block w-full pl-10 pr-3 py-2.5 rounded-full border-none bg-white shadow-card text-sm placeholder-text-muted focus:ring-2 focus:ring-primary/20 transition-all outline-none" placeholder="ค้นหาออเดอร์, สินค้า..." type="text"/>
            </div>
            
            <div class="flex items-center gap-4 lg:gap-6">
                <button class="relative p-2.5 rounded-full bg-white text-text-muted hover:text-primary hover:bg-primary-light transition-colors shadow-card">
                    <span class="material-icons-round">notifications</span>
                    <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white"></span>
                </button>
                <div class="flex items-center gap-3 pl-3 pr-1 py-1 rounded-full bg-white shadow-card cursor-pointer hover:shadow-md transition-shadow">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-bold text-text-main leading-tight">Admin Nina</p>
                        <p class="text-[10px] text-text-muted font-medium">ผู้ดูแลระบบสูงสุด</p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-primary to-secondary p-0.5">
                        <div class="w-full h-full rounded-full bg-white flex items-center justify-center overflow-hidden">
                            <img alt="Admin" class="w-full h-full object-cover" src="https://ui-avatars.com/api/?name=Admin&background=F43F85&color=fff"/>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="p-6 lg:p-8 flex flex-col gap-8 max-w-[1600px] mx-auto w-full">
            
            <div class="relative w-full rounded-3xl overflow-hidden bg-gradient-to-r from-pink-100 to-purple-50 shadow-soft p-8 lg:p-10 flex flex-col md:flex-row items-center justify-between gap-6 border border-pink-200">
                <div class="flex flex-col gap-3 z-10 max-w-lg">
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/80 backdrop-blur-sm w-fit shadow-sm">
                        <span class="w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse"></span>
                        <span class="text-xs font-bold text-gray-700">ระบบทำงานปกติ (อัปเดตล่าสุด: <?= date('H:i') ?> น.)</span>
                    </div>
                    <h1 class="text-3xl md:text-4xl font-extrabold text-gray-800 leading-tight">
                        สวัสดีผู้ดูแลระบบ! <br/>
                        <span class="text-primary">วันนี้ร้านเราเป็นอย่างไรบ้าง?</span>
                    </h1>
                    <p class="text-gray-600 font-medium mt-1">จัดการออเดอร์และดูยอดขายล่าสุดได้ที่นี่ ขอให้เป็นวันที่สดใส!</p>
                </div>
                <div class="absolute -right-10 -bottom-20 w-80 h-80 bg-blue-200/40 rounded-full blur-3xl"></div>
                <div class="absolute -left-10 -top-20 w-80 h-80 bg-primary/20 rounded-full blur-3xl"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
                <div class="bg-white p-6 rounded-3xl shadow-card hover:shadow-soft transition-all duration-300 border border-pink-50">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-14 h-14 rounded-2xl bg-pink-50 flex items-center justify-center text-primary">
                            <span class="material-icons-round text-3xl">payments</span>
                        </div>
                    </div>
                    <p class="text-gray-500 text-sm font-medium mb-1">ยอดขายวันนี้</p>
                    <h3 class="text-3xl font-bold text-gray-800 font-display">฿<?= number_format($todaySales) ?></h3>
                </div>
                <div class="bg-white p-6 rounded-3xl shadow-card hover:shadow-soft transition-all duration-300 border border-pink-50">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-14 h-14 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-500">
                            <span class="material-icons-round text-3xl">local_mall</span>
                        </div>
                    </div>
                    <p class="text-gray-500 text-sm font-medium mb-1">คำสั่งซื้อใหม่ (วันนี้)</p>
                    <h3 class="text-3xl font-bold text-gray-800 font-display"><?= number_format($newOrders) ?> <span class="text-sm text-gray-400 font-normal">รายการ</span></h3>
                </div>
                <div class="bg-white p-6 rounded-3xl shadow-card hover:shadow-soft transition-all duration-300 border border-pink-50">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-14 h-14 rounded-2xl bg-orange-50 flex items-center justify-center text-orange-500">
                            <span class="material-icons-round text-3xl">inventory</span>
                        </div>
                    </div>
                    <p class="text-gray-500 text-sm font-medium mb-1">สินค้าใกล้หมดสต๊อก</p>
                    <h3 class="text-3xl font-bold text-gray-800 font-display"><?= number_format($lowStock) ?> <span class="text-sm text-gray-400 font-normal">รายการ</span></h3>
                </div>
                <div class="bg-white p-6 rounded-3xl shadow-card hover:shadow-soft transition-all duration-300 border border-pink-50">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-14 h-14 rounded-2xl bg-purple-50 flex items-center justify-center text-purple-500">
                            <span class="material-icons-round text-3xl">group</span>
                        </div>
                    </div>
                    <p class="text-gray-500 text-sm font-medium mb-1">สมาชิกรวมทั้งหมด</p>
                    <h3 class="text-3xl font-bold text-gray-800 font-display"><?= number_format($totalUsers) ?> <span class="text-sm text-gray-400 font-normal">คน</span></h3>
                </div>
            </div>

            <div class="bg-white rounded-3xl p-6 lg:p-8 shadow-card border border-pink-50">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">แนวโน้มยอดขายย้อนหลัง</h3>
                        <p class="text-sm text-gray-500 mt-1">ข้อมูล 7 วันล่าสุด</p>
                    </div>
                </div>
                <div class="relative h-[300px] w-full">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-card overflow-hidden border border-pink-50">
                <div class="p-6 lg:p-8 flex items-center justify-between border-b border-gray-100">
                    <h3 class="text-xl font-bold text-gray-800">รายการสั่งซื้อล่าสุด</h3>
                    <a class="text-primary text-sm font-bold hover:underline" href="#">ดูทั้งหมด</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[800px]">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                                <th class="px-6 py-4 font-bold">หมายเลขคำสั่งซื้อ</th>
                                <th class="px-6 py-4 font-bold">ลูกค้า</th>
                                <th class="px-6 py-4 font-bold">สินค้า</th>
                                <th class="px-6 py-4 font-bold">วันที่สั่งซื้อ</th>
                                <th class="px-6 py-4 font-bold">สถานะ</th>
                                <th class="px-6 py-4 font-bold text-right">ยอดรวม</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">ไม่มีรายการสั่งซื้อล่าสุด</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): 
                                    $badge = getBadge($order['status']);
                                    // ดึงตัวอักษร 2 ตัวแรกของชื่อมาทำ Avatar
                                    $initials = mb_substr($order['u_username'] ?? 'User', 0, 2, 'UTF-8');
                                ?>
                                <tr class="hover:bg-pink-50/50 transition-colors border-b border-gray-50 last:border-0">
                                    <td class="px-6 py-4 font-bold text-primary">#<?= htmlspecialchars($order['order_no']) ?></td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($order['u_username'] ?? 'User') ?>&background=random&color=fff" class="w-8 h-8 rounded-full shadow-sm">
                                            <span class="font-bold text-gray-700"><?= htmlspecialchars($order['u_username'] ?? 'ลูกค้าทั่วไป') ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 truncate max-w-[200px]" title="<?= htmlspecialchars($order['product_summary']) ?>">
                                        <?= htmlspecialchars($order['product_summary']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500">
                                        <?= date('d M Y, H:i', strtotime($order['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?= $badge['class'] ?>">
                                            <span class="w-1.5 h-1.5 rounded-full <?= $badge['dot'] ?>"></span> <?= $badge['text'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-gray-800 text-right text-base">
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
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        // ข้อมูลที่ดึงมาจาก PHP
        const labels = <?= json_json_encode($chartLabels) ?>;
        const dataValues = <?= json_encode($chartData) ?>;

        // สร้าง Gradient ให้กราฟดูสวยเหมือนต้นฉบับ
        let gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(244, 63, 133, 0.4)'); // สีชมพูโปร่งแสง
        gradient.addColorStop(1, 'rgba(244, 63, 133, 0.0)'); // ใส

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ยอดขาย (บาท)',
                    data: dataValues,
                    borderColor: '#F43F85',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#F43F85',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4 // ทำให้เส้นโค้งสมูท
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#4a1d35',
                        titleFont: { family: 'Prompt', size: 13 },
                        bodyFont: { family: 'Prompt', size: 14, weight: 'bold' },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return '฿ ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f3f4f6', drawBorder: false },
                        ticks: {
                            font: { family: 'Prompt' }, color: '#9ca3af',
                            callback: function(value) { return '฿' + value.toLocaleString(); }
                        }
                    },
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { font: { family: 'Prompt' }, color: '#9ca3af' }
                    }
                }
            }
        });
    });
</script>
</body>
</html>