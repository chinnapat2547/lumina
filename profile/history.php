<?php
session_start();
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once '../config/connectdbuser.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isset($_SESSION['u_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$isLoggedIn = true;
$u_id = $_SESSION['u_id'];
$profileImage = "https://ui-avatars.com/api/?name=User&background=F43F85&color=fff";

// ==========================================
// 1. ดึงข้อมูล User (Profile Image) และ Cart
// ==========================================
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
// 2. จัดการตัวกรองสถานะ และดึงข้อมูลคำสั่งซื้อ
// ==========================================
$current_status = isset($_GET['status']) ? $_GET['status'] : 'all';

$status_query = "";
if ($current_status === 'pending') $status_query = " AND status = 'pending'";
if ($current_status === 'processing') $status_query = " AND status = 'processing'";
if ($current_status === 'shipped') $status_query = " AND status = 'shipped'";
if ($current_status === 'completed') $status_query = " AND status = 'completed'";

$orders = [];
$sqlOrders = "SELECT * FROM `orders` WHERE u_id = ? $status_query ORDER BY created_at DESC";
if ($stmtOrders = mysqli_prepare($conn, $sqlOrders)) {
    mysqli_stmt_bind_param($stmtOrders, "i", $u_id);
    mysqli_stmt_execute($stmtOrders);
    $resultOrders = mysqli_stmt_get_result($stmtOrders);
    
    while ($order = mysqli_fetch_assoc($resultOrders)) {
        // ดึงรายการสินค้าของแต่ละออเดอร์มาใส่ไว้ใน Array ของออเดอร์นั้นๆ
        $order_id = $order['order_id'];
        $items = [];
        $sqlItems = "SELECT * FROM `order_items` WHERE order_id = ?";
        if ($stmtItems = mysqli_prepare($conn, $sqlItems)) {
            mysqli_stmt_bind_param($stmtItems, "i", $order_id);
            mysqli_stmt_execute($stmtItems);
            $resItems = mysqli_stmt_get_result($stmtItems);
            while($item = mysqli_fetch_assoc($resItems)){
                $items[] = $item;
            }
            mysqli_stmt_close($stmtItems);
        }
        $order['items'] = $items;
        $orders[] = $order;
    }
    mysqli_stmt_close($stmtOrders);
}

// ฟังก์ชันแปลงสถานะเป็นภาษาไทยและสี
function getStatusBadge($status) {
    switch($status) {
        case 'pending': return ['text' => 'ที่ต้องชำระ', 'color' => 'bg-orange-100 text-orange-600 dark:bg-orange-900/30'];
        case 'processing': return ['text' => 'ที่ต้องจัดส่ง', 'color' => 'bg-blue-100 text-blue-600 dark:bg-blue-900/30'];
        case 'shipped': return ['text' => 'ที่ต้องได้รับ', 'color' => 'bg-purple-100 text-purple-600 dark:bg-purple-900/30'];
        case 'completed': return ['text' => 'สำเร็จแล้ว', 'color' => 'bg-green-100 text-green-600 dark:bg-green-900/30'];
        case 'cancelled': return ['text' => 'ยกเลิกแล้ว', 'color' => 'bg-red-100 text-red-600 dark:bg-red-900/30'];
        default: return ['text' => 'ไม่ทราบสถานะ', 'color' => 'bg-gray-100 text-gray-600'];
    }
}
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>ประวัติการสั่งซื้อ - Lumina Beauty</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              primary: "#F43F85",
              secondary: "#FBCFE8",
              accent: "#A78BFA",
              "background-light": "#FFF5F7",
              "background-dark": "#1F1B24",
              "card-light": "#FFFFFF",
              "card-dark": "#2D2635",
              "text-light": "#374151",
              "text-dark": "#E5E7EB",
            },
            fontFamily: {
              display: ["Prompt", "sans-serif"],
              body: ["Prompt", "sans-serif"],
            },
            borderRadius: {
              DEFAULT: "1.5rem", 'xl': '1rem', '2xl': '1.5rem', '3xl': '2rem',
            },
            boxShadow: {
                'soft': '0 10px 40px -10px rgba(244, 63, 133, 0.15)',
            },
          },
        },
      };
    </script>
<style>
        body { font-family: 'Prompt', sans-serif; }
        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .dark .glass-panel {
            background: rgba(45, 38, 53, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark transition-colors duration-300 min-h-screen relative overflow-x-hidden">

<div class="fixed top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
    <div class="absolute -top-[10%] -left-[10%] w-[50%] h-[50%] rounded-full bg-pink-200 dark:bg-pink-900 blur-3xl opacity-30 animate-pulse"></div>
    <div class="absolute top-[40%] -right-[10%] w-[40%] h-[40%] rounded-full bg-purple-200 dark:bg-purple-900 blur-3xl opacity-30"></div>
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
                <span class="absolute -top-1.5 -right-2 bg-primary text-white text-[10px] font-bold rounded-full h-[18px] w-[18px] flex items-center justify-center border-2 border-white dark:border-gray-800">
                    <?= $totalCartItems ?>
                </span>
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

<main class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
    <div class="flex flex-col lg:flex-row gap-8">
        
        <aside class="w-full lg:w-1/4">
            <div class="bg-card-light dark:bg-card-dark rounded-3xl p-6 shadow-soft sticky top-28 border border-transparent dark:border-gray-700">
                <div class="flex flex-col space-y-2">
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="account.php">
                        <span class="material-icons-round">person</span><span>ข้อมูลส่วนตัว</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="manageaccount.php">
                        <span class="material-icons-round text-4xl">manage_accounts</span><span>รายละเอียดบัญชี</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="payment.php">
                        <span class="material-icons-round">credit_card</span><span>วิธีการชำระเงิน</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 bg-pink-50 dark:bg-pink-900/20 text-primary font-medium rounded-2xl transition-all shadow-sm" href="history.php">
                        <span class="material-icons-round">history</span><span>ประวัติการสั่งซื้อ</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="address.php">
                        <span class="material-icons-round">location_on</span><span>ที่อยู่จัดส่ง</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="../shop/favorites.php">
                        <span class="material-icons-round">favorite</span><span>สิ่งที่ถูกใจ</span>
                    </a>
                    <div class="border-t border-gray-100 dark:border-gray-700 my-2 pt-2"></div>
                    <a class="flex items-center space-x-3 px-4 py-3 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-2xl transition-all" href="../auth/logout.php">
                        <span class="material-icons-round">logout</span><span>ออกจากระบบ</span>
                    </a>
                </div>
            </div>
        </aside>

        <section class="w-full lg:w-3/4 space-y-6">
            <div class="bg-gradient-to-r from-pink-400 to-purple-400 rounded-3xl p-8 text-white relative overflow-hidden shadow-lg">
                <div class="relative z-10 flex items-center gap-4">
                    <div class="p-3 bg-white/20 rounded-2xl backdrop-blur-sm">
                        <span class="material-icons-round text-4xl">receipt_long</span>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold font-display">ประวัติการสั่งซื้อ</h1>
                        <p class="text-pink-100 text-sm opacity-90">ตรวจสอบสถานะและรายการย้อนหลังทั้งหมดได้ที่นี่</p>
                    </div>
                </div>
                <div class="absolute -top-10 -right-10 w-40 h-40 bg-white opacity-10 rounded-full blur-2xl"></div>
            </div>

            <div class="flex space-x-3 overflow-x-auto pb-2 scrollbar-hide">
                <a href="?status=all" class="px-6 py-2.5 rounded-full text-sm font-bold whitespace-nowrap transition-all <?= $current_status === 'all' ? 'bg-primary text-white shadow-md' : 'bg-card-light dark:bg-card-dark text-gray-500 hover:text-primary border border-gray-100 dark:border-gray-700' ?>">ทั้งหมด</a>
                <a href="?status=pending" class="px-6 py-2.5 rounded-full text-sm font-bold whitespace-nowrap transition-all <?= $current_status === 'pending' ? 'bg-primary text-white shadow-md' : 'bg-card-light dark:bg-card-dark text-gray-500 hover:text-primary border border-gray-100 dark:border-gray-700' ?>">ที่ต้องชำระ</a>
                <a href="?status=processing" class="px-6 py-2.5 rounded-full text-sm font-bold whitespace-nowrap transition-all <?= $current_status === 'processing' ? 'bg-primary text-white shadow-md' : 'bg-card-light dark:bg-card-dark text-gray-500 hover:text-primary border border-gray-100 dark:border-gray-700' ?>">ที่ต้องจัดส่ง</a>
                <a href="?status=shipped" class="px-6 py-2.5 rounded-full text-sm font-bold whitespace-nowrap transition-all <?= $current_status === 'shipped' ? 'bg-primary text-white shadow-md' : 'bg-card-light dark:bg-card-dark text-gray-500 hover:text-primary border border-gray-100 dark:border-gray-700' ?>">ที่ต้องได้รับ</a>
                <a href="?status=completed" class="px-6 py-2.5 rounded-full text-sm font-bold whitespace-nowrap transition-all <?= $current_status === 'completed' ? 'bg-primary text-white shadow-md' : 'bg-card-light dark:bg-card-dark text-gray-500 hover:text-primary border border-gray-100 dark:border-gray-700' ?>">สำเร็จแล้ว</a>
            </div>

            <?php if (empty($orders)): ?>
                <div class="flex flex-col items-center justify-center py-16 text-center bg-card-light dark:bg-card-dark rounded-3xl shadow-soft border border-transparent dark:border-gray-700">
                    <div class="w-40 h-40 mb-6 bg-pink-50 dark:bg-gray-800 rounded-full flex items-center justify-center">
                        <span class="material-icons-round text-primary text-6xl opacity-50">shopping_bag</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">คุณยังไม่มีประวัติการสั่งซื้อ</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-8">เริ่มช้อปเลย! เพื่อรับสินค้าดีๆ จาก Lumina Beauty</p>
                    <a href="../shop/products.php" class="px-8 py-3.5 rounded-full bg-primary text-white hover:bg-pink-600 shadow-lg shadow-pink-200 dark:shadow-none text-base font-bold transition-all transform hover:-translate-y-0.5">
                        ไปช้อปปิ้งกันเลย
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($orders as $order): 
                        $badge = getStatusBadge($order['status']);
                        $formattedDate = date('d M Y, H:i', strtotime($order['created_at']));
                    ?>
                        <div class="bg-card-light dark:bg-card-dark rounded-3xl p-6 shadow-soft border border-gray-100 dark:border-gray-700 hover:shadow-lg transition-shadow">
                            <div class="flex justify-between items-center border-b border-gray-100 dark:border-gray-700 pb-4 mb-4">
                                <div>
                                    <span class="text-sm font-bold text-gray-800 dark:text-white mr-2">ออเดอร์: <?= htmlspecialchars($order['order_no']) ?></span>
                                    <span class="text-xs text-gray-500"><?= $formattedDate ?></span>
                                </div>
                                <div class="px-3 py-1 rounded-full text-xs font-bold <?= $badge['color'] ?>">
                                    <?= $badge['text'] ?>
                                </div>
                            </div>
                            
                            <div class="space-y-4 mb-6">
                                <?php foreach ($order['items'] as $item): 
                                    $imgUrl = (!empty($item['p_image']) && file_exists("../uploads/products/" . $item['p_image'])) 
                                                ? "../uploads/products/" . $item['p_image'] 
                                                : "https://via.placeholder.com/150";
                                ?>
                                    <div class="flex gap-4 items-center">
                                        <div class="w-20 h-20 rounded-2xl bg-gray-50 dark:bg-gray-800 overflow-hidden flex-shrink-0 border border-gray-100 dark:border-gray-700">
                                            <img src="<?= $imgUrl ?>" class="w-full h-full object-cover">
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-bold text-gray-800 dark:text-white text-sm md:text-base line-clamp-1"><?= htmlspecialchars($item['p_name']) ?></h4>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">x<?= $item['quantity'] ?></p>
                                        </div>
                                        <div class="font-bold text-primary">
                                            ฿<?= number_format($item['price']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="flex flex-col sm:flex-row justify-between items-center pt-4 border-t border-gray-100 dark:border-gray-700 gap-4">
                                <div class="text-sm text-gray-600 dark:text-gray-400 font-medium">
                                    ยอดคำสั่งซื้อทั้งหมด: <span class="text-lg font-bold text-primary font-display ml-1">฿<?= number_format($order['total_amount']) ?></span>
                                </div>
                                <div class="flex gap-3 w-full sm:w-auto">
                                    <a href="#" class="flex-1 sm:flex-none px-6 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-bold hover:bg-gray-50 dark:hover:bg-gray-700 text-center transition-colors">ดูรายละเอียด</a>
                                    <?php if ($order['status'] === 'completed'): ?>
                                        <a href="#" class="flex-1 sm:flex-none px-6 py-2.5 rounded-xl bg-primary hover:bg-pink-600 text-white text-sm font-bold text-center transition-colors shadow-md">ซื้ออีกครั้ง</a>
                                    <?php elseif ($order['status'] === 'pending'): ?>
                                        <a href="payment.php" class="flex-1 sm:flex-none px-6 py-2.5 rounded-xl bg-primary hover:bg-pink-600 text-white text-sm font-bold text-center transition-colors shadow-md">ชำระเงิน</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </section>
    </div>
</main>

<script>
    if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        localStorage.setItem('theme', htmlEl.classList.contains('dark') ? 'dark' : 'light');
    }
</script>
</body></html>