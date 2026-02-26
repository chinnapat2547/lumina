<?php
session_start();
require_once '../config/connectdbuser.php';

// ==========================================
// 1. จัดการข้อมูลผู้ใช้สำหรับแสดงบน Navbar
// ==========================================
$isLoggedIn = false;
$profileImage = "https://ui-avatars.com/api/?name=Guest&background=E5E7EB&color=9CA3AF"; 
$userData = ['u_username' => 'ผู้เยี่ยมชม', 'u_email' => 'กรุณาเข้าสู่ระบบ'];

if (isset($_SESSION['u_id'])) {
    $isLoggedIn = true;
    $u_id = $_SESSION['u_id'];
    
    $sqlUser = "SELECT a.u_username, a.u_email, u.u_image 
            FROM `account` a 
            LEFT JOIN `user` u ON a.u_id = u.u_id 
            WHERE a.u_id = ?";
    if ($stmtUser = mysqli_prepare($conn, $sqlUser)) {
        mysqli_stmt_bind_param($stmtUser, "i", $u_id);
        mysqli_stmt_execute($stmtUser);
        $resultUser = mysqli_stmt_get_result($stmtUser);
        if ($rowUser = mysqli_fetch_assoc($resultUser)) {
            $userData = $rowUser;
            if (!empty($userData['u_image']) && file_exists("../profile/uploads/" . $userData['u_image'])) {
                $profileImage = "../profile/uploads/" . $userData['u_image'];
            } else {
                $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($userData['u_username']) . "&background=F43F85&color=fff";
            }
        }
        mysqli_stmt_close($stmtUser);
    }
}

// ==========================================
// 2. จัดการตัวกรอง (Filters), ค้นหา และ แบ่งหน้า
// ==========================================
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 5000;
$selected_cats = isset($_GET['category']) ? $_GET['category'] : []; // อันนี้จะรับเป็น Array ID หมวดหมู่

$limit = 20; 
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// 🟢 แก้ไขเงื่อนไข WHERE: ให้แสดงเฉพาะสินค้าที่เปิดขาย (status = 1)
$where_clauses = ["status = 1"];
$params = [];
$types = "";

if ($search_query !== '') {
    $where_clauses[] = "(p_name LIKE ? OR p_sku LIKE ?)";
    $params[] = "%" . $search_query . "%";
    $params[] = "%" . $search_query . "%";
    $types .= "ss";
}

if ($max_price > 0 && $max_price < 5000) {
    $where_clauses[] = "p_price <= ?";
    $params[] = $max_price;
    $types .= "d";
}

// 🟢 กรองหมวดหมู่: แก้ไขตรงนี้ ระบุ p.c_id เพื่อป้องกัน Ambiguous column
if (!empty($selected_cats)) {
    $placeholders = implode(',', array_fill(0, count($selected_cats), '?'));
    $where_clauses[] = "p.c_id IN ($placeholders)"; // แก้จาก c_id เป็น p.c_id
    foreach ($selected_cats as $cat) {
        $params[] = (int)$cat; 
        $types .= "i";
    }
}

$where_sql = implode(" AND ", $where_clauses);

// เรียงลำดับ
$order_sql = "ORDER BY p_id DESC";
if ($sort_by === 'price_asc') $order_sql = "ORDER BY p_price ASC";
elseif ($sort_by === 'price_desc') $order_sql = "ORDER BY p_price DESC";
elseif ($sort_by === 'popular') $order_sql = "ORDER BY p_id ASC"; 

// ==========================================
// 3. ดึงข้อมูลจาก Database
// ==========================================
$total_products = 0;
$products = [];

// คิวรีนับจำนวน (ต้อง Join เหมือนกันเพื่อให้เงื่อนไขถูกต้อง)
$sqlCount = "SELECT COUNT(p.p_id) as total FROM `product` p LEFT JOIN `category` c ON p.c_id = c.c_id WHERE $where_sql";
$stmtCount = mysqli_prepare($conn, $sqlCount);
if ($types) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$total_products = $resultCount->fetch_assoc()['total'];
$stmtCount->close();

$total_pages = ceil($total_products / $limit);

// 🟢 ดึงข้อมูลสินค้า (Join ตาราง category มาเอาชื่อหมวดหมู่ด้วย)
$sqlData = "
    SELECT p.*, c.c_name 
    FROM `product` p 
    LEFT JOIN `category` c ON p.c_id = c.c_id 
    WHERE $where_sql 
    $order_sql 
    LIMIT ? OFFSET ?
";
$stmtData = mysqli_prepare($conn, $sqlData);
$typesData = $types . "ii";
$paramsData = $params;
$paramsData[] = $limit;
$paramsData[] = $offset;
$stmtData->bind_param($typesData, ...$paramsData);
$stmtData->execute();
$resultData = $stmtData->get_result();
while ($row = $resultData->fetch_assoc()) {
    $products[] = $row;
}
$stmtData->close();

$totalCartItems = 0;
if (isset($u_id)) {
    $sqlCartCount = "SELECT SUM(quantity) as total_qty FROM `cart` WHERE u_id = ?";
    if ($stmtCartCount = mysqli_prepare($conn, $sqlCartCount)) {
        mysqli_stmt_bind_param($stmtCartCount, "i", $u_id);
        mysqli_stmt_execute($stmtCartCount);
        $resultCartCount = mysqli_stmt_get_result($stmtCartCount);
        if ($rowCartCount = mysqli_fetch_assoc($resultCartCount)) {
            $totalCartItems = $rowCartCount['total_qty'] ?? 0;
        }
        mysqli_stmt_close($stmtCartCount);
    }
}

// 🟢 ดึงรายการหมวดหมู่ทั้งหมดสำหรับทำตัวกรองซ้ายมือ
$categories_list = [];
$resCat = mysqli_query($conn, "SELECT * FROM category");
while($c = mysqli_fetch_assoc($resCat)) { 
    $categories_list[] = $c; 
}
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>สินค้าทั้งหมด - Lumina Beauty</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
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
              "surface-light": "#FFFFFF",
              "surface-dark": "#2D2635",
              "text-light": "#374151",
              "text-dark": "#E5E7EB",
            },
            fontFamily: {
              display: ["Prompt", "sans-serif"],
              body: ["Prompt", "sans-serif"],
            },
            borderRadius: {
              DEFAULT: "1rem",
              'xl': "1.5rem",
              '2xl': "2rem",
              '3xl': "3rem",
            },
            boxShadow: {
              'soft': '0 10px 40px -10px rgba(244, 63, 133, 0.15)',
              'glow': '0 0 20px rgba(244, 63, 133, 0.3)',
            }
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
        input[type=range] {
            -webkit-appearance: none;
            width: 100%;
            background: transparent;
        }
        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            height: 20px; width: 20px; border-radius: 50%;
            background: #F43F85; cursor: pointer; margin-top: -8px;
            box-shadow: 0 0 10px rgba(244, 63, 133, 0.4);
        }
        input[type=range]::-webkit-slider-runnable-track {
            width: 100%; height: 4px; cursor: pointer;
            background: #FBCFE8; border-radius: 2px;
        }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #FBCFE8; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #F43F85; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark transition-colors duration-300 min-h-screen relative overflow-x-hidden">

<div class="fixed top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
    <div class="absolute top-[10%] left-[10%] w-[40%] h-[40%] rounded-full bg-pink-200 dark:bg-pink-900 blur-[100px] opacity-40 animate-pulse"></div>
    <div class="absolute bottom-[10%] right-[10%] w-[40%] h-[40%] rounded-full bg-purple-200 dark:bg-purple-900 blur-[100px] opacity-30 animate-pulse" style="animation-delay: 2s;"></div>
</div>

<header class="sticky top-0 z-50 glass-panel shadow-sm px-6 py-4 mb-8 relative z-50">
    <div class="w-full px-4 md:px-10 lg:px-16"> 
        <div class="flex justify-between items-center h-20 w-full">
        <a href="../home.php" class="flex items-center space-x-2 cursor-pointer hover:opacity-80 transition-opacity">
            <span class="material-icons-round text-primary text-4xl">spa</span>
            <span class="font-bold text-2xl tracking-tight text-primary">Lumina</span>
        </a>

        <div class="hidden lg:flex gap-8 xl:gap-12 items-center justify-center flex-grow ml-20">
            <a class="flex flex-col items-center justify-center cursor-default pointer-events-none">
                <span class="text-[18px] font-bold text-primary dark:text-primary leading-tight">สินค้า</span>
                <span class="text-[13px] text-primary/80 dark:text-primary/80">(Shop)</span>
            </a>
            
            <div class="relative group">
                <button class="flex flex-col items-center justify-center transition pb-2 pt-2">
                    <div class="flex items-center gap-1">
                        <span class="text-[18px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">หมวดหมู่</span>
                        <span class="material-icons-round text-sm text-gray-700 dark:text-gray-200 group-hover:text-primary">expand_more</span>
                    </div>
                    <span class="text-[13px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Categories)</span>
                </button>
                <div class="absolute left-1/2 -translate-x-1/2 hidden pt-1 w-48 z-50 group-hover:block">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden py-2">
                        <?php foreach($categories_list as $c): ?>
                            <a href="?category[]=<?= $c['c_id'] ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-pink-50 dark:hover:bg-gray-700 hover:text-primary transition"><?= htmlspecialchars($c['c_name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <a class="group flex flex-col items-center justify-center transition" href="promotions.php">
                <span class="text-[18px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">โปรโมชั่น</span>
                <span class="text-[13px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Sale)</span>
            </a>
            <a class="group flex flex-col items-center justify-center transition" href="../auth/contact.php">
                <span class="text-[18px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">ติดต่อเรา</span>
                <span class="text-[13px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Contact)</span>
            </a>
        </div>

        <div class="flex items-center space-x-3 sm:space-x-5 text-gray-600 dark:text-gray-300">
            <div class="hidden xl:block relative group">
                <form action="products.php" method="GET">
                    <input id="liveSearchInput" name="search" value="<?= htmlspecialchars($search_query) ?>" class="pl-10 pr-4 py-2 rounded-full border border-pink-200 dark:border-gray-700 bg-input-bg dark:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent text-sm w-48 xl:w-56 transition-all relative z-10" placeholder="ค้นหาสินค้า..." type="text" autocomplete="off"/>
                    <button type="submit" class="material-icons-round absolute left-3 top-2 text-gray-400 z-10">search</button>
                </form>
            </div>
            <a href="favorites.php" class="hover:text-primary transition relative flex items-center">
                <span class="material-icons-round text-2xl">favorite_border</span>
            </a>
            <a href="cart.php" class="hover:text-primary transition relative flex items-center">
                <span class="material-icons-round text-2xl">shopping_bag</span>
                <span class="absolute -top-1.5 -right-2 bg-primary text-white text-[10px] font-bold rounded-full h-[18px] w-[18px] flex items-center justify-center border-2 border-white dark:border-gray-800">
                    <?= $totalCartItems ?>
                </span>
            </a>
            
            <button class="hover:text-primary transition flex items-center justify-center" onclick="toggleTheme()">
                <span class="material-icons-round dark:hidden text-2xl text-gray-500">dark_mode</span>
                <span class="material-icons-round hidden dark:block text-yellow-400 text-2xl">light_mode</span>
            </button>

            <div class="relative group flex items-center">
                <a href="../profile/account.php" class="block w-10 h-10 rounded-full bg-gradient-to-tr from-pink-300 to-purple-300 p-0.5 shadow-sm hover:shadow-md hover:scale-105 transition-all cursor-pointer">
                    <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-full h-full">
                        <img alt="Profile" class="w-full h-full rounded-full object-cover" src="<?= htmlspecialchars($profileImage) ?>"/>
                    </div>
                </a>
                <div class="absolute right-0 hidden pt-4 top-full w-[320px] z-50 group-hover:block cursor-default">
                    <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-[0_10px_40px_-10px_rgba(236,45,136,0.2)] border border-pink-100 dark:border-gray-700 overflow-hidden p-5 relative">
                        
                        <div class="text-center mb-4">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                <?= $isLoggedIn ? htmlspecialchars($userData['u_email']) : 'กรุณาเข้าสู่ระบบเพื่อใช้งาน' ?>
                            </span>
                        </div>

                        <div class="flex justify-center relative mb-4">
                            <div class="rounded-full p-[3px] bg-primary shadow-md">
                                <div class="bg-white dark:bg-gray-800 rounded-full p-[3px] w-16 h-16">
                                    <img src="<?= htmlspecialchars($profileImage) ?>" alt="Profile" class="w-full h-full rounded-full object-cover">
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-2 mb-6">
                            <h3 class="text-[22px] font-bold text-gray-800 dark:text-white">สวัสดีคุณ <?= htmlspecialchars($userData['u_username']) ?></h3>
                        </div>

                        <div class="flex flex-col gap-3 mt-2">
                            <?php if($isLoggedIn): ?>
                                <a href="../profile/account.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-primary hover:bg-primary hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-primary">
                                    จัดการบัญชี
                                </a>
                                <a href="../auth/logout.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-red-500 hover:bg-red-500 hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-red-500">
                                    <span class="material-icons-round text-[20px]">logout</span> ออกจากระบบ
                                </a>
                            <?php else: ?>
                                <a href="../auth/login.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-primary hover:bg-primary hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-primary">
                                    <span class="material-icons-round text-[20px]">login</span> เข้าสู่ระบบ
                                </a>
                                <a href="../auth/register.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-primary hover:bg-primary hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-primary">
                                    <span class="material-icons-round text-[20px]">person_add</span> สมัครสมาชิก
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="flex justify-center items-center gap-2 mt-5 text-[11px] text-gray-400">
                            <a href="#" class="hover:text-primary">นโยบายความเป็นส่วนตัว</a>
                            <span>•</span>
                            <a href="#" class="hover:text-primary">ข้อกำหนดบริการ</a>
                        </div>
                    </div>
                </div>
            </div>

    </div>
</header>

<main class="relative z-10 w-full min-h-[calc(100vh-80px)] pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 relative z-20">
            <div class="mb-4 md:mb-0 text-center md:text-left">
                <div class="inline-flex items-center gap-2 bg-white dark:bg-surface-dark px-6 py-2 rounded-full shadow-soft border border-pink-50 dark:border-gray-800">
                    <span class="material-icons-round text-primary text-2xl">grid_view</span>
                    <h1 class="text-2xl font-display font-bold text-gray-800 dark:text-white">สินค้าทั้งหมด</h1>
                </div>
                <p class="text-gray-500 dark:text-gray-400 mt-3 ml-2 font-medium">พบสินค้า <?= number_format($total_products) ?> รายการ</p>
            </div>
            
            <div class="relative">
                <div class="flex items-center space-x-2 bg-white dark:bg-surface-dark px-4 py-2.5 rounded-full shadow-soft border border-pink-50 dark:border-gray-800">
                    <span class="text-gray-500 text-sm material-icons-round text-[18px]">sort</span>
                    <span class="text-gray-500 text-sm font-medium">เรียงตาม:</span>
                    <select id="sortSelect" onchange="applyFilters()" class="bg-transparent border-none text-sm font-bold text-gray-800 dark:text-white focus:ring-0 pr-6 py-0 cursor-pointer outline-none">
                        <option value="latest" <?= $sort_by == 'latest' ? 'selected' : '' ?>>ล่าสุด</option>
                        <option value="popular" <?= $sort_by == 'popular' ? 'selected' : '' ?>>ยอดนิยม</option>
                        <option value="price_asc" <?= $sort_by == 'price_asc' ? 'selected' : '' ?>>ราคา: ต่ำไปสูง</option>
                        <option value="price_desc" <?= $sort_by == 'price_desc' ? 'selected' : '' ?>>ราคา: สูงไปต่ำ</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            
            <aside class="w-full lg:w-64 flex-shrink-0">
                <div class="bg-white dark:bg-surface-dark rounded-3xl p-6 shadow-soft sticky top-28 border border-pink-50 dark:border-gray-800">
                    <div class="flex items-center justify-between mb-6 border-b border-gray-100 dark:border-gray-700 pb-4">
                        <h3 class="font-display font-bold text-lg text-gray-800 dark:text-white flex items-center gap-2">
                            <span class="material-icons-round text-primary text-[20px]">filter_alt</span> ตัวกรอง
                        </h3>
                        <span onclick="window.location.href='products.php'" class="text-sm text-gray-400 cursor-pointer hover:text-primary transition-colors">ล้างค่า</span>
                    </div>

                    <div class="mb-8">
                        <h4 class="font-bold text-gray-800 dark:text-gray-200 mb-4 text-sm">หมวดหมู่สินค้า</h4>
                        <ul class="space-y-3">
                            <?php foreach($categories_list as $cat): 
                                $checked = in_array($cat['c_id'], $selected_cats) ? 'checked' : '';
                            ?>
                            <li>
                                <label class="flex items-center group cursor-pointer">
                                    <input type="checkbox" value="<?= $cat['c_id'] ?>" class="cat-checkbox rounded text-primary focus:ring-primary border-gray-300 dark:border-gray-600 dark:bg-gray-700 mr-3 w-4 h-4 transition-all" onchange="applyFilters()" <?= $checked ?>>
                                    <span class="text-gray-600 dark:text-gray-400 group-hover:text-primary transition-colors text-sm"><?= htmlspecialchars($cat['c_name']) ?></span>
                                </label>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div>
                        <h4 class="font-bold text-gray-800 dark:text-gray-200 mb-4 text-sm">ราคาไม่เกิน</h4>
                        <div class="px-2">
                            <input class="w-full mb-4" max="5000" min="0" step="100" type="range" id="priceRange" value="<?= $max_price ?>" onchange="applyFilters()" oninput="document.getElementById('priceValue').textContent = '฿' + Number(this.value).toLocaleString()"/>
                            <div class="flex justify-between items-center text-sm font-medium text-gray-600 dark:text-gray-300">
                                <span class="bg-pink-50 dark:bg-gray-800 text-primary px-3 py-1 rounded-lg">฿0</span>
                                <span class="text-gray-400">-</span>
                                <span class="bg-pink-50 dark:bg-gray-800 text-primary px-3 py-1 rounded-lg" id="priceValue">฿<?= number_format($max_price) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="flex-1">
                <?php if ($total_products > 0): ?>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                        
                        <?php foreach($products as $p): 
                            $p_id = $p['p_id'];
                            $p_name = htmlspecialchars($p['p_name']);
                            $p_price = number_format($p['p_price']);
                            // เช็คว่ามีรูปหลักไหม ถ้าไม่มีให้ใช้ placeholder
                            $p_image = (!empty($p['p_image']) && file_exists("../uploads/products/" . $p['p_image'])) 
                                        ? "../uploads/products/" . $p['p_image'] 
                                        : "https://via.placeholder.com/400x400.png?text=No+Image";
                        ?>
                            <div class="bg-white dark:bg-surface-dark rounded-[24px] p-4 shadow-soft hover:shadow-glow transition-all duration-300 group hover:-translate-y-2 relative flex flex-col border border-transparent dark:border-gray-700">
                                
                                <form action="favorites.php" method="POST" class="absolute top-4 right-4 z-10">
                                    <input type="hidden" name="action" value="add_fav">
                                    <input type="hidden" name="p_id" value="<?= $p_id ?>">
                                    <button type="submit" class="text-gray-300 hover:text-primary hover:scale-110 transition-all bg-white/50 dark:bg-black/30 rounded-full p-1 backdrop-blur-sm shadow-sm" title="เพิ่มในสิ่งที่ถูกใจ">
                                        <span class="material-icons-round text-2xl">favorite_border</span>
                                    </button>
                                </form>
                                
                                <a href="productdetail.php?id=<?= $p_id ?>" class="w-full aspect-square bg-gray-50 dark:bg-gray-800 rounded-xl overflow-hidden mb-4 relative flex items-center justify-center block cursor-pointer">
                                    <img alt="<?= $p_name ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="<?= $p_image ?>"/>
                                    <?php if($p['p_stock'] <= 0): ?>
                                        <div class="absolute inset-0 bg-black/40 flex items-center justify-center backdrop-blur-sm">
                                            <span class="bg-white text-gray-800 font-bold px-4 py-1.5 rounded-full shadow-lg">สินค้าหมด</span>
                                        </div>
                                    <?php endif; ?>
                                </a>
                                
                                <div class="flex-1 flex flex-col px-1">
                                    <h3 class="text-md font-display font-bold text-gray-800 dark:text-white mb-1 leading-tight line-clamp-1" title="<?= $p_name ?>"><?= $p_name ?></h3>
                                    
                                    <div class="flex items-center mb-2">
                                        <span class="material-icons-round text-yellow-400 text-[12px]">star</span>
                                        <span class="material-icons-round text-yellow-400 text-[12px]">star</span>
                                        <span class="material-icons-round text-yellow-400 text-[12px]">star</span>
                                        <span class="material-icons-round text-yellow-400 text-[12px]">star</span>
                                        <span class="material-icons-round text-yellow-400 text-[12px]">star_half</span>
                                    </div>
                                    
                                    <div class="mt-auto flex justify-between items-center mb-3">
                                        <span class="text-lg font-bold text-primary">฿<?= $p_price ?></span>
                                    </div>
                                    
                                    <form action="cart.php" method="POST" class="mt-auto">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="p_id" value="<?= $p_id ?>">
                                        <?php if($p['p_stock'] > 0): ?>
                                            <button type="submit" class="w-full bg-pink-50 dark:bg-gray-800 text-primary dark:text-pink-400 hover:bg-primary hover:text-white py-2 rounded-xl font-bold text-sm transition-colors flex items-center justify-center gap-1">
                                                <span class="material-icons-round text-[16px]">shopping_cart</span> ใส่ตะกร้า
                                            </button>
                                        <?php else: ?>
                                            <button disabled class="w-full bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 py-2 rounded-xl font-bold text-sm flex items-center justify-center gap-1 cursor-not-allowed">
                                                <span class="material-icons-round text-[16px]">remove_shopping_cart</span> สินค้าหมด
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <div class="mt-12 flex justify-center items-center space-x-2">
                        <?php if ($page > 1): ?>
                        <a href="javascript:goToPage(<?= $page - 1 ?>)" class="w-10 h-10 rounded-full bg-white dark:bg-surface-dark shadow-sm flex items-center justify-center text-gray-500 hover:text-primary transition-colors border border-gray-100 dark:border-gray-700">
                            <span class="material-icons-round">chevron_left</span>
                        </a>
                        <?php endif; ?>

                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <button class="w-10 h-10 rounded-full bg-primary text-white shadow-md flex items-center justify-center font-bold">
                                    <?= $i ?>
                                </button>
                            <?php else: ?>
                                <a href="javascript:goToPage(<?= $i ?>)" class="w-10 h-10 rounded-full bg-white dark:bg-surface-dark shadow-sm flex items-center justify-center text-gray-500 hover:text-primary hover:bg-pink-50 transition-all font-medium border border-gray-100 dark:border-gray-700">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                        <a href="javascript:goToPage(<?= $page + 1 ?>)" class="w-10 h-10 rounded-full bg-white dark:bg-surface-dark shadow-sm flex items-center justify-center text-gray-500 hover:text-primary transition-colors border border-gray-100 dark:border-gray-700">
                            <span class="material-icons-round">chevron_right</span>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="bg-white dark:bg-surface-dark rounded-3xl p-16 shadow-soft text-center border border-gray-100 dark:border-gray-700 flex flex-col items-center justify-center min-h-[400px]">
                        <div class="w-24 h-24 bg-pink-50 dark:bg-gray-800 rounded-full flex items-center justify-center mb-6 text-primary opacity-80">
                            <span class="material-icons-round text-6xl">search_off</span>
                        </div>
                        <h3 class="text-2xl font-display font-bold text-gray-800 dark:text-white mb-2">ไม่พบสินค้าที่คุณค้นหา</h3>
                        <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto">ลองเปลี่ยนเงื่อนไขตัวกรอง หมวดหมู่ หรือช่วงราคาดูอีกครั้ง</p>
                        <button onclick="window.location.href='products.php'" class="mt-6 px-6 py-2 bg-primary text-white rounded-full font-medium hover:bg-pink-600 transition">ล้างตัวกรองทั้งหมด</button>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</main>

<footer class="bg-white dark:bg-surface-dark py-10 border-t border-pink-50 dark:border-gray-800 mt-auto relative z-20">
    <div class="max-w-7xl mx-auto px-4 text-center">
        <div class="flex justify-center items-center mb-4 opacity-80">
            <span class="text-primary material-icons-round text-4xl">spa</span>
            <span class="ml-2 text-xl font-bold text-gray-800 dark:text-white">Lumina Beauty</span>
        </div>
        <p class="text-gray-500 dark:text-gray-400 text-sm">© 2024 Lumina Beauty. All rights reserved.</p>
    </div>
</footer>

<script>
    // Toggle Dark Mode
    function toggleTheme() {
        const html = document.documentElement;
        if (html.classList.contains('dark')) {
            html.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        } else {
            html.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        }
    }

    // Check Local Storage for Theme
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }

    function applyFilters() {
        const search = document.getElementById('liveSearchInput').value;
        const sort = document.getElementById('sortSelect').value;
        const max_price = document.getElementById('priceRange').value;
        
        const checkboxes = document.querySelectorAll('.cat-checkbox:checked');
        let cats = [];
        checkboxes.forEach((cb) => {
            cats.push('category[]=' + cb.value);
        });
        const catString = cats.join('&');

        let url = `products.php?search=${encodeURIComponent(search)}&sort=${sort}&max_price=${max_price}`;
        if(catString) {
            url += `&${catString}`;
        }
        window.location.href = url;
    }

    function goToPage(page) {
        const search = document.getElementById('liveSearchInput').value;
        const sort = document.getElementById('sortSelect').value;
        const max_price = document.getElementById('priceRange').value;

        const checkboxes = document.querySelectorAll('.cat-checkbox:checked');
        let cats = [];
        checkboxes.forEach((cb) => {
            cats.push('category[]=' + cb.value);
        });
        const catString = cats.join('&');

        let url = `products.php?page=${page}&search=${encodeURIComponent(search)}&sort=${sort}&max_price=${max_price}`;
        if(catString) {
            url += `&${catString}`;
        }
        window.location.href = url;
    }
</script>

</body>
</html>