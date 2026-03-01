<?php
session_start();
require_once '../config/connectdbuser.php';

// ==========================================
// 1. จัดการข้อมูลผู้ใช้และ Admin สำหรับ Navbar
// ==========================================
$isLoggedIn = false;
$isAdmin = false;
$profileImage = "https://ui-avatars.com/api/?name=Guest&background=E5E7EB&color=9CA3AF"; 
$userData = ['u_username' => 'ผู้เยี่ยมชม', 'u_email' => 'กรุณาเข้าสู่ระบบ'];
$totalCartItems = 0;
$userFavs = [];

if (isset($_SESSION['admin_id'])) {
    $isLoggedIn = true;
    $isAdmin = true;
    $userData['u_username'] = $_SESSION['admin_username'] ?? 'Admin';
    $userData['u_email'] = 'Administrator Mode';
    $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($userData['u_username']) . "&background=a855f7&color=fff";
} elseif (isset($_SESSION['u_id'])) {
    $isLoggedIn = true;
    $u_id = $_SESSION['u_id'];
    
    $sqlUser = "SELECT a.u_username, a.u_email, u.u_image FROM `account` a LEFT JOIN `user` u ON a.u_id = u.u_id WHERE a.u_id = ?";
    if ($stmtUser = mysqli_prepare($conn, $sqlUser)) {
        mysqli_stmt_bind_param($stmtUser, "i", $u_id);
        mysqli_stmt_execute($stmtUser);
        $resultUser = mysqli_stmt_get_result($stmtUser);
        if ($rowUser = mysqli_fetch_assoc($resultUser)) {
            $userData = $rowUser;
            $physical_path = __DIR__ . "/../profile/uploads/" . $userData['u_image'];
            if (!empty($userData['u_image']) && file_exists($physical_path)) {
                $profileImage = "../profile/uploads/" . $userData['u_image'];
            } else {
                $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($userData['u_username']) . "&background=F43F85&color=fff";
            }
        }
        mysqli_stmt_close($stmtUser);
    }

    $sqlCartCount = "SELECT SUM(quantity) as total_qty FROM `cart` WHERE u_id = ?";
    if ($stmtCartCount = mysqli_prepare($conn, $sqlCartCount)) {
        mysqli_stmt_bind_param($stmtCartCount, "i", $u_id);
        mysqli_stmt_execute($stmtCartCount);
        $resCart = mysqli_stmt_get_result($stmtCartCount);
        if ($rowCart = mysqli_fetch_assoc($resCart)) {
            $totalCartItems = $rowCart['total_qty'] ?? 0;
        }
        mysqli_stmt_close($stmtCartCount);
    }

    $resFav = mysqli_query($conn, "SELECT p_id FROM favorites WHERE u_id = $u_id");
    while($f = mysqli_fetch_assoc($resFav)) {
        $userFavs[] = $f['p_id'];
    }
}

// ==========================================
// 2. ดึงข้อมูลหมวดหมู่ (Category Info)
// ==========================================
$c_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($c_id <= 0) {
    header("Location: products.php");
    exit();
}

$categoryName = "ไม่พบหมวดหมู่";
$sqlCat = "SELECT c_name FROM category WHERE c_id = ?";
if ($stmtCat = mysqli_prepare($conn, $sqlCat)) {
    mysqli_stmt_bind_param($stmtCat, "i", $c_id);
    mysqli_stmt_execute($stmtCat);
    $resCatInfo = mysqli_stmt_get_result($stmtCat);
    if ($rowCat = mysqli_fetch_assoc($resCatInfo)) {
        $categoryName = $rowCat['c_name'];
    } else {
        header("Location: products.php"); // ถ้าไม่มี ID นี้ ให้กลับไปหน้าสินค้าทั้งหมด
        exit();
    }
    mysqli_stmt_close($stmtCat);
}

// ==========================================
// 3. จัดการการเรียงลำดับและแบ่งหน้า (Pagination)
// ==========================================
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
$limit = 12; 
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$order_sql = "ORDER BY p_id DESC";
if ($sort_by === 'price_asc') $order_sql = "ORDER BY p_price ASC";
elseif ($sort_by === 'price_desc') $order_sql = "ORDER BY p_price DESC";
elseif ($sort_by === 'popular') $order_sql = "ORDER BY p_id ASC"; 

$total_products = 0;
$products = [];

// นับจำนวนสินค้าในหมวดหมู่นี้
$sqlCount = "SELECT COUNT(p_id) as total FROM `product` WHERE c_id = ? AND status = 1";
if ($stmtCount = mysqli_prepare($conn, $sqlCount)) {
    mysqli_stmt_bind_param($stmtCount, "i", $c_id);
    mysqli_stmt_execute($stmtCount);
    $resultCount = mysqli_stmt_get_result($stmtCount);
    $total_products = $resultCount->fetch_assoc()['total'];
    mysqli_stmt_close($stmtCount);
}
$total_pages = ceil($total_products / $limit);

// ดึงรายการสินค้า
$sqlData = "SELECT p_id, p_name, p_price, p_image, p_stock FROM `product` WHERE c_id = ? AND status = 1 $order_sql LIMIT ? OFFSET ?";
if ($stmtData = mysqli_prepare($conn, $sqlData)) {
    mysqli_stmt_bind_param($stmtData, "iii", $c_id, $limit, $offset);
    mysqli_stmt_execute($stmtData);
    $resultData = mysqli_stmt_get_result($stmtData);
    while ($row = $resultData->fetch_assoc()) {
        $products[] = $row;
    }
    mysqli_stmt_close($stmtData);
}

// ดึงสีของสินค้า (สำหรับป๊อปอัปตะกร้า)
$product_colors = [];
if (!empty($products)) {
    $p_ids = array_column($products, 'p_id');
    $id_list = implode(',', $p_ids);
    $resCol = mysqli_query($conn, "SELECT p_id, color_name, color_hex FROM product_colors WHERE p_id IN ($id_list)");
    while($col = mysqli_fetch_assoc($resCol)) {
        $product_colors[$col['p_id']][] = $col;
    }
}

// ดึงรายการหมวดหมู่ทั้งหมดสำหรับ Navbar
$categories_list = [];
$resAllCat = mysqli_query($conn, "SELECT * FROM category");
while($c = mysqli_fetch_assoc($resAllCat)) { $categories_list[] = $c; }
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?= htmlspecialchars($categoryName) ?> - Lumina Beauty</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              primary: "#F43F85",
              secondary: "#FBCFE8",
              "background-light": "#FFF5F7",
              "background-dark": "#1F1B24",
              "surface-light": "#FFFFFF",
              "surface-dark": "#2D2635",
            },
            fontFamily: { display: ["Prompt", "sans-serif"], body: ["Prompt", "sans-serif"] },
            boxShadow: { 'soft': '0 10px 40px -10px rgba(244, 63, 133, 0.15)', 'glow': '0 0 20px rgba(244, 63, 133, 0.3)' }
          }
        }
      };
    </script>
<style>
        body { font-family: 'Prompt', sans-serif; }
        .glass-panel { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.5); }
        .dark .glass-panel { background: rgba(45, 38, 53, 0.7); border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #FBCFE8; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #F43F85; }
        input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        .flying-img { position: fixed; z-index: 9999; border-radius: 50%; opacity: 0.8; transition: all 0.8s cubic-bezier(0.25, 1, 0.5, 1); pointer-events: none; box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        .hero-gradient { background: linear-gradient(135deg, #fce7f3 0%, #e0e7ff 100%); }
        .dark .hero-gradient { background: linear-gradient(135deg, #4c1d95 0%, #831843 100%); }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-gray-700 dark:text-gray-200 transition-colors duration-300 min-h-screen flex flex-col relative overflow-x-hidden">

<header class="sticky top-0 z-50 glass-panel shadow-sm px-6 py-4 mb-4 relative">
    <div class="w-full px-4 md:px-10 lg:px-16"> 
        <div class="flex justify-between items-center h-10 w-full">
            <a href="../home.php" class="flex items-center space-x-2 cursor-pointer hover:opacity-80 transition-opacity">
                <span class="material-icons-round text-primary text-4xl">spa</span>
                <span class="font-bold text-2xl tracking-tight text-primary">Lumina</span>
            </a>

            <div class="hidden lg:flex gap-8 xl:gap-12 items-center justify-center flex-grow ml-20">
                <a href="products.php" class="group flex flex-col items-center justify-center transition">
                    <span class="text-[18px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary leading-tight">สินค้า</span>
                    <span class="text-[13px] text-gray-500 dark:text-gray-400 group-hover:text-primary">(Shop)</span>
                </a>
                <div class="relative group">
                    <button class="flex flex-col items-center justify-center transition pb-2 pt-2">
                        <div class="flex items-center gap-1">
                            <span class="text-[18px] font-bold text-primary leading-tight">หมวดหมู่</span>
                            <span class="material-icons-round text-sm text-primary">expand_more</span>
                        </div>
                        <span class="text-[13px] text-primary/80">(Categories)</span>
                    </button>
                    <div class="absolute left-1/2 -translate-x-1/2 hidden pt-1 w-48 z-50 group-hover:block">
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden py-2">
                            <?php foreach($categories_list as $c): ?>
                                <a href="category.php?id=<?= $c['c_id'] ?>" class="block px-4 py-2 text-sm <?= $c_id == $c['c_id'] ? 'text-primary bg-pink-50 dark:bg-gray-700 font-bold' : 'text-gray-700 dark:text-gray-300 hover:bg-pink-50 dark:hover:bg-gray-700 hover:text-primary' ?> transition"><?= htmlspecialchars($c['c_name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <a class="group flex flex-col items-center justify-center transition" href="promotions.php">
                    <span class="text-[18px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary leading-tight">โปรโมชั่น</span>
                    <span class="text-[13px] text-gray-500 dark:text-gray-400 group-hover:text-primary">(Sale)</span>
                </a>
                <a class="group flex flex-col items-center justify-center transition" href="../contact.php">
                    <span class="text-[18px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary leading-tight">ติดต่อเรา</span>
                    <span class="text-[13px] text-gray-500 dark:text-gray-400 group-hover:text-primary">(Contact)</span>
                </a>
            </div>

            <div class="flex items-center space-x-3 sm:space-x-5">
                <div class="hidden xl:block relative group">
                    <form action="products.php" method="GET" class="relative">
                        <input id="liveSearchInput" name="search" onkeyup="liveSearch(this.value)" class="pl-10 pr-4 py-2 rounded-full border border-pink-200 dark:border-gray-700 bg-white dark:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-primary text-sm w-48 xl:w-56 transition-all relative z-10" placeholder="ค้นหาสินค้า..." type="text" autocomplete="off"/>
                        <span class="material-icons-round absolute left-3 top-2 text-gray-400 z-10 pointer-events-none">search</span>
                        <div id="searchResultBox" class="absolute top-[110%] left-0 w-[150%] bg-white dark:bg-gray-800 shadow-2xl rounded-2xl overflow-hidden hidden z-[100] border border-gray-100 dark:border-gray-700 max-h-[400px] overflow-y-auto"></div>
                    </form>
                </div>
                
                <a href="favorites.php" id="nav-fav-icon" class="text-gray-500 dark:text-gray-300 hover:text-pink-600 transition relative flex items-center justify-center group">
                    <span class="material-icons-round text-2xl transition-transform duration-300 group-hover:scale-110">favorite_border</span>
                </a>
                
                <a href="cart.php" id="nav-cart-icon" class="relative w-10 h-10 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-800 rounded-full transition-all cursor-pointer">
                    <span class="material-icons-round text-2xl transition-transform duration-300">shopping_bag</span>
                    <span id="cart-badge" class="absolute -top-1.5 -right-2 bg-primary text-white text-[10px] font-bold rounded-full h-[18px] w-[18px] flex items-center justify-center border-2 border-white dark:border-gray-800 transition-transform duration-300"><?= $totalCartItems ?></span>
                </a>
                
                <button class="hover:text-primary transition flex items-center justify-center" onclick="toggleTheme()">
                    <span class="material-icons-round dark:hidden text-2xl text-gray-500">dark_mode</span>
                    <span class="material-icons-round hidden dark:block text-yellow-400 text-2xl">light_mode</span>
                </button>

                <div class="relative group flex items-center">
                    <a href="<?= $isAdmin ? '../admin/dashboard.php' : '../profile/account.php' ?>" class="block w-10 h-10 rounded-full bg-gradient-to-tr <?= $isAdmin ? 'from-purple-400 to-indigo-400' : 'from-pink-300 to-purple-300' ?> p-0.5 shadow-sm hover:shadow-md hover:scale-105 transition-all cursor-pointer">
                        <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-full h-full">
                            <img alt="Profile" class="w-full h-full rounded-full object-cover" src="<?= htmlspecialchars($profileImage) ?>"/>
                        </div>
                    </a>
                    <div class="absolute right-0 hidden pt-4 top-full w-[320px] z-50 group-hover:block cursor-default">
                        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-[0_10px_40px_-10px_rgba(236,45,136,0.2)] border border-pink-100 dark:border-gray-700 overflow-hidden p-5 relative">
                            <div class="text-center mb-4">
                                <span class="text-sm font-medium <?= $isAdmin ? 'text-purple-500 font-bold' : 'text-gray-500 dark:text-gray-400' ?>">
                                    <?= $isAdmin ? 'Administrator Mode' : ($isLoggedIn ? htmlspecialchars($userData['u_email']) : 'กรุณาเข้าสู่ระบบ') ?>
                                </span>
                            </div>
                            <div class="flex justify-center relative mb-4">
                                <div class="rounded-full p-[3px] <?= $isAdmin ? 'bg-purple-500' : 'bg-primary' ?> shadow-md">
                                    <div class="bg-white dark:bg-gray-800 rounded-full p-[3px] w-16 h-16">
                                        <img src="<?= htmlspecialchars($profileImage) ?>" alt="Profile" class="w-full h-full rounded-full object-cover">
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mt-2 mb-6">
                                <h3 class="text-[22px] font-bold text-gray-800 dark:text-white">สวัสดี คุณ <?= htmlspecialchars($userData['u_username']) ?></h3>
                            </div>
                            <div class="flex flex-col gap-3 mt-2">
                            <?php if($isAdmin): ?>
                                <a href="admin/dashboard.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-purple-500 hover:bg-purple-500 hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-purple-500">
                                    <span class="material-icons-round text-[20px]">admin_panel_settings</span> สำหรับ Admin
                                </a>
                            <?php elseif($isLoggedIn): ?>
                                <a href="profile/account.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-primary hover:bg-primary hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-primary">
                                    จัดการบัญชี
                                </a>
                            <?php else: ?>
                                <a href="auth/login.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-primary hover:bg-primary hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-primary">
                                    <span class="material-icons-round text-[20px]">login</span> เข้าสู่ระบบ
                                </a>
                                <a href="auth/register.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-primary hover:bg-primary hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-primary">
                                    <span class="material-icons-round text-[20px]">person_add</span> สมัครสมาชิก
                                </a>
                            <?php endif; ?>
                            
                            <?php if($isLoggedIn): ?>
                            <a href="auth/logout.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-red-500 hover:bg-red-500 hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-red-500">
                                <span class="material-icons-round text-[20px]">logout</span> ออกจากระบบ
                            </a>
                            <?php endif; ?>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full mb-20">
    
    <div class="hero-gradient rounded-[2.5rem] p-8 md:p-12 mb-10 shadow-soft relative overflow-hidden flex flex-col md:flex-row items-center justify-between border border-white/50 dark:border-gray-700">
        <div class="absolute top-[-20%] right-[-10%] opacity-20 pointer-events-none">
            <span class="material-icons-round text-[250px] text-white">category</span>
        </div>
        <div class="relative z-10 text-center md:text-left">
            <div class="inline-block bg-white/60 dark:bg-gray-800/60 backdrop-blur-md px-4 py-1.5 rounded-full text-primary dark:text-pink-400 font-bold text-sm mb-3 shadow-sm border border-white/50 dark:border-gray-600">
                หมวดหมู่สินค้า
            </div>
            <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 dark:text-white tracking-tight mb-2"><?= htmlspecialchars($categoryName) ?></h1>
            <p class="text-gray-600 dark:text-gray-300 font-medium">พบสินค้าในหมวดหมู่นี้ทั้งหมด <span class="text-primary font-bold"><?= number_format($total_products) ?></span> รายการ</p>
        </div>
    </div>

    <div class="flex justify-end mb-6 relative z-20">
        <div class="flex items-center space-x-2 bg-white dark:bg-surface-dark px-4 py-2.5 rounded-full shadow-soft border border-pink-50 dark:border-gray-800">
            <span class="text-gray-500 text-sm material-icons-round text-[18px]">sort</span>
            <span class="text-gray-500 text-sm font-medium">เรียงตาม:</span>
            <select id="sortSelect" onchange="window.location.href='category.php?id=<?= $c_id ?>&sort='+this.value" class="bg-transparent border-none text-sm font-bold text-gray-800 dark:text-white focus:ring-0 pr-6 py-0 cursor-pointer outline-none">
                <option value="latest" <?= $sort_by == 'latest' ? 'selected' : '' ?>>ล่าสุด</option>
                <option value="popular" <?= $sort_by == 'popular' ? 'selected' : '' ?>>ยอดนิยม</option>
                <option value="price_asc" <?= $sort_by == 'price_asc' ? 'selected' : '' ?>>ราคา: ต่ำไปสูง</option>
                <option value="price_desc" <?= $sort_by == 'price_desc' ? 'selected' : '' ?>>ราคา: สูงไปต่ำ</option>
            </select>
        </div>
    </div>

    <?php if ($total_products > 0): ?>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach($products as $p): 
                $p_id = $p['p_id'];
                $p_name = htmlspecialchars($p['p_name']);
                $p_price = number_format($p['p_price']);
                $p_image = (!empty($p['p_image']) && file_exists("../uploads/products/" . $p['p_image'])) 
                            ? "../uploads/products/" . $p['p_image'] 
                            : "https://via.placeholder.com/400x400.png?text=No+Image";
                
                $isFav = in_array($p_id, $userFavs);
                $colorsData = $product_colors[$p_id] ?? [];
                $colorsJson = htmlspecialchars(json_encode($colorsData), ENT_QUOTES, 'UTF-8');
            ?>
                <div class="bg-white dark:bg-surface-dark rounded-[24px] p-4 shadow-soft hover:shadow-glow transition-all duration-300 group hover:-translate-y-2 relative flex flex-col border border-transparent dark:border-gray-700">
                    
                    <button type="button" onclick="toggleFavAjax(<?= $p_id ?>, this)" class="absolute top-4 right-4 z-10 text-gray-300 hover:text-primary hover:scale-110 transition-all bg-white/50 dark:bg-black/30 rounded-full p-1 backdrop-blur-sm shadow-sm <?= $isFav ? 'text-primary' : '' ?>" title="เพิ่มในสิ่งที่ถูกใจ">
                        <span class="material-icons-round text-2xl fav-icon"><?= $isFav ? 'favorite' : 'favorite_border' ?></span>
                    </button>
                    
                    <a href="productdetail.php?id=<?= $p_id ?>" class="w-full aspect-square bg-gray-50 dark:bg-gray-800 rounded-xl overflow-hidden mb-4 relative flex items-center justify-center block cursor-pointer">
                        <img id="img-prod-<?= $p_id ?>" alt="<?= $p_name ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="<?= $p_image ?>"/>
                        <?php if($p['p_stock'] <= 0): ?>
                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center backdrop-blur-sm">
                                <span class="bg-white text-gray-800 font-bold px-4 py-1.5 rounded-full shadow-lg">สินค้าหมด</span>
                            </div>
                        <?php endif; ?>
                    </a>
                    
                    <div class="flex-1 flex flex-col px-1">
                        <h3 class="text-md font-display font-bold text-gray-800 dark:text-white mb-1 leading-tight line-clamp-2" title="<?= $p_name ?>"><?= $p_name ?></h3>
                        <div class="mt-auto flex justify-between items-center mb-3 pt-2">
                            <span class="text-lg font-bold text-primary">฿<?= $p_price ?></span>
                        </div>
                        
                        <?php if($p['p_stock'] > 0): ?>
                            <button type="button" onclick="openQuickCart(<?= $p_id ?>, '<?= addslashes($p_name) ?>', <?= $p['p_price'] ?>, '<?= $p_image ?>', <?= $p['p_stock'] ?>, '<?= $colorsJson ?>')" class="w-full bg-pink-50 dark:bg-gray-800 text-primary dark:text-pink-400 hover:bg-primary hover:text-white py-2 rounded-xl font-bold text-sm transition-colors flex items-center justify-center gap-1">
                                <span class="material-icons-round text-[16px]">shopping_cart</span> ใส่ตะกร้า
                            </button>
                        <?php else: ?>
                            <button disabled class="w-full bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 py-2 rounded-xl font-bold text-sm flex items-center justify-center gap-1 cursor-not-allowed">
                                <span class="material-icons-round text-[16px]">remove_shopping_cart</span> สินค้าหมด
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="mt-12 flex justify-center items-center space-x-2">
            <?php if ($page > 1): ?>
            <a href="category.php?id=<?= $c_id ?>&page=<?= $page - 1 ?>&sort=<?= $sort_by ?>" class="w-10 h-10 rounded-full bg-white dark:bg-surface-dark shadow-sm flex items-center justify-center text-gray-500 hover:text-primary transition-colors border border-gray-100 dark:border-gray-700">
                <span class="material-icons-round">chevron_left</span>
            </a>
            <?php endif; ?>

            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <button class="w-10 h-10 rounded-full bg-primary text-white shadow-md flex items-center justify-center font-bold">
                        <?= $i ?>
                    </button>
                <?php else: ?>
                    <a href="category.php?id=<?= $c_id ?>&page=<?= $i ?>&sort=<?= $sort_by ?>" class="w-10 h-10 rounded-full bg-white dark:bg-surface-dark shadow-sm flex items-center justify-center text-gray-500 hover:text-primary hover:bg-pink-50 transition-all font-medium border border-gray-100 dark:border-gray-700">
                        <?= $i ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="category.php?id=<?= $c_id ?>&page=<?= $page + 1 ?>&sort=<?= $sort_by ?>" class="w-10 h-10 rounded-full bg-white dark:bg-surface-dark shadow-sm flex items-center justify-center text-gray-500 hover:text-primary transition-colors border border-gray-100 dark:border-gray-700">
                <span class="material-icons-round">chevron_right</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="bg-white dark:bg-surface-dark rounded-3xl p-16 shadow-soft text-center border border-gray-100 dark:border-gray-700 flex flex-col items-center justify-center min-h-[400px]">
            <div class="w-24 h-24 bg-pink-50 dark:bg-gray-800 rounded-full flex items-center justify-center mb-6 text-primary opacity-80">
                <span class="material-icons-round text-6xl">category</span>
            </div>
            <h3 class="text-2xl font-display font-bold text-gray-800 dark:text-white mb-2">ยังไม่มีสินค้าในหมวดหมู่นี้</h3>
            <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto">ทางเรากำลังเตรียมนำสินค้าคอลเลกชันใหม่เข้ามาเร็วๆ นี้ โปรดติดตาม!</p>
            <a href="products.php" class="mt-6 px-6 py-3 bg-primary text-white rounded-full font-medium hover:bg-pink-600 transition shadow-md">ดูสินค้าหมวดหมู่ทั้งหมด</a>
        </div>
    <?php endif; ?>

</main>

<footer class="bg-white dark:bg-surface-dark py-10 border-t border-pink-50 dark:border-gray-800 mt-auto relative z-20 w-full">
    <div class="max-w-7xl mx-auto px-4 text-center">
        <div class="flex justify-center items-center mb-4 opacity-80">
            <span class="text-primary material-icons-round text-4xl">spa</span>
            <span class="ml-2 text-xl font-bold text-gray-800 dark:text-white">Lumina Beauty</span>
        </div>
        <p class="text-gray-500 dark:text-gray-400 text-sm">© 2026 Lumina Beauty. All rights reserved.</p>
    </div>
</footer>

<div id="quickCartModal" class="fixed inset-0 z-[100] hidden bg-black/60 backdrop-blur-sm flex items-center justify-center opacity-0 transition-opacity duration-300 px-4">
    <div class="bg-white dark:bg-gray-800 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl transform scale-95 transition-transform duration-300 modal-content">
        <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800">
            <h3 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <span class="material-icons-round text-primary">add_shopping_cart</span> เพิ่มลงตะกร้า
            </h3>
            <button onclick="closeQuickCart()" class="w-8 h-8 rounded-full bg-white dark:bg-gray-700 text-gray-400 hover:text-red-500 flex items-center justify-center shadow-sm border border-gray-100 dark:border-gray-600 transition-colors">
                <span class="material-icons-round text-sm">close</span>
            </button>
        </div>

        <form id="quickCartForm" onsubmit="submitQuickCart(event)" class="p-6">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="p_id" id="qc_p_id">
            
            <div class="flex gap-4 mb-6">
                <img id="qc_img" src="" class="w-20 h-20 rounded-xl object-cover border border-gray-100 dark:border-gray-700 shadow-sm" alt="Product">
                <div class="flex-1">
                    <h4 id="qc_name" class="font-bold text-gray-800 dark:text-white text-sm line-clamp-2 mb-1"></h4>
                    <p id="qc_price" class="text-primary font-bold text-lg"></p>
                </div>
            </div>

            <div id="qc_color_section" class="mb-6 hidden">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-xs text-gray-500 font-bold">ตัวเลือกสี:</span>
                    <span id="qc_color_name" class="text-primary font-medium text-xs"></span>
                </div>
                <div id="qc_color_container" class="flex flex-wrap gap-2"></div>
                <input type="hidden" name="selected_color" id="qc_selected_color">
            </div>

            <div class="flex items-center justify-between mb-8">
                <span class="text-sm font-bold text-gray-700 dark:text-gray-300">จำนวน</span>
                <div class="flex items-center bg-gray-50 dark:bg-gray-700 rounded-full p-1 border border-gray-200 dark:border-gray-600">
                    <button type="button" onclick="adjustQuickQty(-1)" class="w-8 h-8 rounded-full bg-white dark:bg-gray-600 shadow-sm flex items-center justify-center text-gray-600 dark:text-white hover:text-primary transition-colors">
                        <span class="material-icons-round text-sm">remove</span>
                    </button>
                    <input type="number" name="qty" id="qc_qty" value="1" min="1" class="w-12 text-center font-bold text-gray-900 dark:text-white bg-transparent border-none outline-none focus:ring-0 p-0 text-sm pointer-events-none" readonly>
                    <button type="button" onclick="adjustQuickQty(1)" class="w-8 h-8 rounded-full bg-white dark:bg-gray-600 shadow-sm flex items-center justify-center text-gray-600 dark:text-white hover:text-primary transition-colors">
                        <span class="material-icons-round text-sm">add</span>
                    </button>
                </div>
            </div>

            <button type="submit" class="w-full bg-primary hover:bg-pink-600 text-white font-bold py-3.5 rounded-full shadow-lg shadow-primary/30 transition-transform transform hover:-translate-y-0.5">
                ยืนยันใส่ตะกร้า
            </button>
        </form>
    </div>
</div>

<script>
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

    // Theme
    function toggleTheme() {
        const html = document.documentElement;
        if (html.classList.contains('dark')) {
            html.classList.remove('dark'); localStorage.setItem('theme', 'light');
        } else {
            html.classList.add('dark'); localStorage.setItem('theme', 'dark');
        }
    }
    if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');

    // Live Search
    function liveSearch(query) {
        const resultBox = document.getElementById('searchResultBox');
        if (query.trim().length === 0) {
            resultBox.innerHTML = ''; resultBox.classList.add('hidden'); return;
        }
        fetch('ajax_search.php?q=' + encodeURIComponent(query))
            .then(response => response.text())
            .then(html => {
                resultBox.innerHTML = html; resultBox.classList.remove('hidden');
            });
    }
    document.addEventListener('click', function(e) {
        const searchInput = document.getElementById('liveSearchInput');
        const resultBox = document.getElementById('searchResultBox');
        if (searchInput && resultBox && !searchInput.contains(e.target) && !resultBox.contains(e.target)) {
            resultBox.classList.add('hidden');
        }
    });

    // Toggle Fav
    function toggleFavAjax(p_id, btnElement) {
        if(!isLoggedIn) {
            Swal.fire({ icon: 'warning', title: 'กรุณาเข้าสู่ระบบ', confirmButtonColor: '#ec2d88', customClass:{popup:'rounded-3xl'} }).then(()=> { window.location.href = '../auth/login.php'; });
            return;
        }
        const iconSpan = btnElement.querySelector('.fav-icon');
        const isFav = iconSpan.innerText === 'favorite';
        const navHeart = document.querySelector('#nav-fav-icon span');

        if (isFav) {
            iconSpan.innerText = 'favorite_border';
            btnElement.classList.remove('text-primary');
            navHeart.classList.add('scale-90');
            setTimeout(() => navHeart.classList.remove('scale-90'), 200);
        } else {
            iconSpan.innerText = 'favorite';
            btnElement.classList.add('text-primary');
            flyToIcon('nav-fav-icon', document.getElementById('img-prod-' + p_id));
            setTimeout(() => { navHeart.classList.add('scale-125', 'text-primary'); setTimeout(() => navHeart.classList.remove('scale-125', 'text-primary'), 300); }, 800);
        }

        const formData = new FormData();
        formData.append('action', 'toggle_fav');
        formData.append('p_id', p_id);
        fetch('favorites.php', { method: 'POST', body: formData });
    }

    function flyToIcon(targetIconId, sourceImg) {
        const targetIcon = document.getElementById(targetIconId);
        if (!sourceImg || !targetIcon) return;
        const flyingImg = sourceImg.cloneNode();
        const srcRect = sourceImg.getBoundingClientRect();
        const targetRect = targetIcon.getBoundingClientRect();
        flyingImg.classList.add('flying-img');
        flyingImg.style.left = srcRect.left + 'px';
        flyingImg.style.top = srcRect.top + 'px';
        flyingImg.style.width = srcRect.width + 'px';
        flyingImg.style.height = srcRect.height + 'px';
        document.body.appendChild(flyingImg);
        setTimeout(() => {
            flyingImg.style.left = (targetRect.left + targetRect.width / 2 - 20) + 'px';
            flyingImg.style.top = (targetRect.top + targetRect.height / 2 - 20) + 'px';
            flyingImg.style.width = '40px'; flyingImg.style.height = '40px'; flyingImg.style.opacity = '0';
        }, 10);
        setTimeout(() => { flyingImg.remove(); }, 800);
    }

    // Quick Cart
    let currentMaxStock = 1;
    let currentSourceImage = null;

    function openQuickCart(p_id, name, price, imgUrl, stock, colorsJson) {
        if(!isLoggedIn) {
            Swal.fire({ icon: 'warning', title: 'กรุณาเข้าสู่ระบบ', confirmButtonColor: '#ec2d88', customClass:{popup:'rounded-3xl'} }).then(()=> { window.location.href = '../auth/login.php'; });
            return;
        }

        currentMaxStock = stock;
        currentSourceImage = document.getElementById('img-prod-' + p_id);

        document.getElementById('qc_p_id').value = p_id;
        document.getElementById('qc_name').innerText = name;
        document.getElementById('qc_price').innerText = '฿' + parseFloat(price).toLocaleString('th-TH');
        document.getElementById('qc_img').src = imgUrl;
        document.getElementById('qc_qty').value = 1;

        const colors = JSON.parse(colorsJson);
        const colorSec = document.getElementById('qc_color_section');
        const colorContainer = document.getElementById('qc_color_container');
        colorContainer.innerHTML = '';
        
        if(colors && colors.length > 0) {
            colorSec.classList.remove('hidden');
            document.getElementById('qc_color_name').innerText = colors[0].color_name;
            document.getElementById('qc_selected_color').value = colors[0].color_name;

            colors.forEach((c, index) => {
                const btn = document.createElement('button');
                btn.type = 'button'; btn.title = c.color_name;
                btn.className = `qc-color-swatch relative w-8 h-8 rounded-full border-2 focus:outline-none flex items-center justify-center transition-all duration-200 ${index===0 ? 'ring-2 ring-primary scale-110 shadow-md border-white dark:border-gray-800' : 'ring-1 ring-gray-200 border-white hover:scale-110'}`;
                btn.style.backgroundColor = c.color_hex;
                btn.onclick = () => selectQuickColor(btn, c.color_name);
                const icon = document.createElement('span');
                icon.className = `material-icons-round text-white text-[16px] drop-shadow-md ${index===0 ? 'block' : 'hidden'}`;
                icon.innerText = 'check';
                btn.appendChild(icon); colorContainer.appendChild(btn);
            });
        } else {
            colorSec.classList.add('hidden');
            document.getElementById('qc_selected_color').value = '';
        }

        const modal = document.getElementById('quickCartModal');
        modal.classList.remove('hidden'); modal.classList.add('flex');
        setTimeout(() => { modal.classList.remove('opacity-0'); modal.querySelector('.modal-content').classList.remove('scale-95'); }, 10);
    }

    function closeQuickCart() {
        const modal = document.getElementById('quickCartModal');
        modal.classList.add('opacity-0'); modal.querySelector('.modal-content').classList.add('scale-95');
        setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 300);
    }

    function adjustQuickQty(amount) {
        const input = document.getElementById('qc_qty');
        let newVal = parseInt(input.value) + amount;
        if (newVal >= 1 && newVal <= currentMaxStock) input.value = newVal;
    }

    function selectQuickColor(btn, colorName) {
        document.getElementById('qc_selected_color').value = colorName;
        document.getElementById('qc_color_name').innerText = colorName;
        const swatches = document.querySelectorAll('.qc-color-swatch');
        swatches.forEach(s => {
            s.classList.remove('ring-2', 'ring-primary', 'scale-110', 'shadow-md', 'border-white', 'dark:border-gray-800');
            s.classList.add('ring-1', 'ring-gray-200', 'border-white', 'hover:scale-110');
            s.querySelector('span').classList.replace('block', 'hidden');
        });
        btn.classList.remove('ring-1', 'ring-gray-200', 'hover:scale-110');
        btn.classList.add('ring-2', 'ring-primary', 'scale-110', 'shadow-md', 'border-white', 'dark:border-gray-800');
        btn.querySelector('span').classList.replace('hidden', 'block');
    }

    function submitQuickCart(e) {
        e.preventDefault();
        const formData = new FormData(document.getElementById('quickCartForm'));
        const qty = parseInt(document.getElementById('qc_qty').value);

        fetch('cart.php', { method: 'POST', body: formData }).then(res => {
            if(res.ok) {
                closeQuickCart(); flyToIcon('nav-cart-icon', currentSourceImage);
                setTimeout(() => {
                    const badge = document.getElementById('cart-badge');
                    let currentBadgeQty = parseInt(badge.innerText) || 0;
                    badge.innerText = currentBadgeQty + qty;
                    const navCart = document.querySelector('#nav-cart-icon');
                    navCart.classList.add('scale-125', 'text-primary');
                    setTimeout(() => navCart.classList.remove('scale-125', 'text-primary'), 300);
                }, 800);
            }
        });
    }
</script>

</body>
</html>