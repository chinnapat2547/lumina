<?php
session_start();
require_once '../config/connectdbuser.php';

// ==========================================
// 1. จัดการข้อมูลผู้ใช้และตะกร้า (สำหรับ Navbar)
// ==========================================
$isLoggedIn = false;
$profileImage = "https://ui-avatars.com/api/?name=Guest&background=E5E7EB&color=9CA3AF"; 
$userData = ['u_username' => 'ผู้เยี่ยมชม', 'u_email' => 'กรุณาเข้าสู่ระบบ'];
$totalCartItems = 0;
$isAdmin = isset($_SESSION['admin_id']) ? true : false;

if (isset($_SESSION['u_id'])) {
    $isLoggedIn = true;
    $u_id = $_SESSION['u_id'];
    
    // ดึงข้อมูลผู้ใช้
    $sqlUser = "SELECT a.u_username, a.u_email, u.u_image FROM `account` a LEFT JOIN `user` u ON a.u_id = u.u_id WHERE a.u_id = ?";
    if ($stmtUser = mysqli_prepare($conn, $sqlUser)) {
        mysqli_stmt_bind_param($stmtUser, "i", $u_id);
        mysqli_stmt_execute($stmtUser);
        $resultUser = mysqli_stmt_get_result($stmtUser);
        if ($rowUser = mysqli_fetch_assoc($resultUser)) {
            $userData = $rowUser;
            if (!empty($userData['u_image']) && file_exists("../uploads/" . $userData['u_image'])) {
                $profileImage = "../uploads/" . $userData['u_image'];
            } else {
                $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($displayName) . "&background=F43F85&color=fff";
            }
        }
        mysqli_stmt_close($stmtUser);
    }
    
    // นับจำนวนสินค้าในตะกร้า
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

// ==========================================
// 2. ดึงข้อมูลสินค้าที่ระบุตาม ID
// ==========================================
$p_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($p_id <= 0) {
    header("Location: products.php");
    exit();
}

$product = null;
$sqlProduct = "SELECT p.*, c.c_name as p_category FROM `product` p LEFT JOIN `category` c ON p.c_id = c.c_id WHERE p.p_id = ?";
if ($stmt = mysqli_prepare($conn, $sqlProduct)) {
    mysqli_stmt_bind_param($stmt, "i", $p_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

if (!$product) {
    header("Location: products.php");
    exit();
}

// ==========================================
// 3. ดึงรูปภาพแกลเลอรีของสินค้านี้
// ==========================================
$images = [];
$mainImg = (!empty($product['p_image']) && file_exists("../uploads/products/" . $product['p_image'])) 
            ? "../uploads/products/" . $product['p_image'] 
            : "https://via.placeholder.com/600x600.png?text=No+Image";
$images[] = $mainImg;

$sqlImages = "SELECT image_url FROM `product_images` WHERE p_id = ?";
if ($stmtImg = mysqli_prepare($conn, $sqlImages)) {
    mysqli_stmt_bind_param($stmtImg, "i", $p_id);
    mysqli_stmt_execute($stmtImg);
    $resultImg = mysqli_stmt_get_result($stmtImg);
    while ($rowImg = mysqli_fetch_assoc($resultImg)) {
        $imgPath = "../uploads/products/" . $rowImg['image_url'];
        if (file_exists($imgPath) && $imgPath !== $mainImg) {
            $images[] = $imgPath;
        }
    }
    mysqli_stmt_close($stmtImg);
}

// ==========================================
// 4. ดึงข้อมูลสีของสินค้า
// ==========================================
$colors = [];
$sqlColors = "SELECT color_name, color_hex FROM `product_colors` WHERE p_id = ?";
if ($stmtCol = mysqli_prepare($conn, $sqlColors)) {
    mysqli_stmt_bind_param($stmtCol, "i", $p_id);
    mysqli_stmt_execute($stmtCol);
    $resultCol = mysqli_stmt_get_result($stmtCol);
    while ($rowCol = mysqli_fetch_assoc($resultCol)) {
        $colors[] = $rowCol;
    }
    mysqli_stmt_close($stmtCol);
}

// ==========================================
// 5. ดึงสินค้าแนะนำ (สุ่มมา 4 รายการ)
// ==========================================
$recommended = [];
$sqlRec = "SELECT p_id, p_name, p_price, p_image, p_detail FROM `product` WHERE p_id != ? AND status = 1 ORDER BY RAND() LIMIT 4";
if ($stmtRec = mysqli_prepare($conn, $sqlRec)) {
    mysqli_stmt_bind_param($stmtRec, "i", $p_id);
    mysqli_stmt_execute($stmtRec);
    $resultRec = mysqli_stmt_get_result($stmtRec);
    while ($rowRec = mysqli_fetch_assoc($resultRec)) {
        $recommended[] = $rowRec;
    }
    mysqli_stmt_close($stmtRec);
}
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?= htmlspecialchars($product['p_name']) ?> - Lumina Beauty</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
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
              body: ["Prompt", "sans-serif"]
            },
            borderRadius: {
              DEFAULT: "1rem", "lg": "2rem", "xl": "3rem", "2xl": "4rem", "full": "9999px"
            },
            boxShadow: {
              'soft': '0 20px 40px -15px rgba(244, 63, 133, 0.15)',
              'glow': '0 0 20px rgba(244, 63, 133, 0.3)',
            }
          },
        },
      }
    </script>
<style>
        body { font-family: 'Prompt', sans-serif; }
        
        .glass-panel {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(244, 63, 133, 0.1);
        }
        .dark .glass-panel {
            background: rgba(31, 27, 36, 0.85);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
          -webkit-appearance: none; margin: 0; 
        }

        /* สำหรับแอนิเมชันลอยเข้าตะกร้า */
        .flying-img {
            position: fixed;
            z-index: 9999;
            border-radius: 50%;
            opacity: 0.8;
            transition: all 0.8s cubic-bezier(0.25, 1, 0.5, 1);
            pointer-events: none;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-pink-50 dark:from-background-dark dark:to-gray-900 min-h-screen text-gray-800 dark:text-gray-100 font-display transition-colors duration-300">

<header class="sticky top-0 z-50 glass-panel shadow-sm px-6 py-4 mb-8 relative">
    <div class="w-full px-4 md:px-10 lg:px-16"> 
        <div class="flex justify-between items-center h-10 w-full">
            <a href="../home.php" class="flex items-center space-x-2 cursor-pointer hover:opacity-80 transition-opacity">
                <span class="material-icons-round text-primary text-4xl">spa</span>
                <span class="font-bold text-2xl tracking-tight text-primary font-display">Lumina</span>
            </a>
            
            <div class="hidden lg:flex gap-8 xl:gap-12 items-center justify-center flex-grow ml-10">
                <a class="group flex flex-col items-center justify-center transition" href="products.php">
                    <span class="text-[16px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">สินค้า</span>
                    <span class="text-[12px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Shop)</span>
                </a>
                <div class="relative group">
                    <button class="flex flex-col items-center justify-center transition pb-1 pt-1">
                        <div class="flex items-center gap-1">
                            <span class="text-[16px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">หมวดหมู่</span>
                            <span class="material-icons-round text-sm text-gray-700 dark:text-gray-200 group-hover:text-primary">expand_more</span>
                        </div>
                        <span class="text-[12px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Categories)</span>
                    </button>
                </div>
                <a class="group flex flex-col items-center justify-center transition" href="promotions.php">
                    <span class="text-[16px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">โปรโมชั่น</span>
                    <span class="text-[12px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Sale)</span>
                </a>
                <a class="group flex flex-col items-center justify-center transition" href="../contact.php">
                    <span class="text-[16px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">ติดต่อเรา</span>
                    <span class="text-[12px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Contact)</span>
                </a>
            </div>

            <div class="flex items-center space-x-2 sm:space-x-4">
                <a href="favorites.php" id="nav-fav-icon" class="text-gray-500 dark:text-gray-300 hover:text-pink-600 transition relative flex items-center justify-center">
                    <span class="material-icons-round text-2xl transition-transform duration-300">favorite_border</span>
                </a>
                <a href="cart.php" id="nav-cart-icon" class="relative w-10 h-10 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-800 rounded-full transition-all cursor-pointer">
                    <span class="material-icons-round text-2xl transition-transform duration-300">shopping_bag</span>
                    <span id="cart-badge" class="absolute -top-1.5 -right-2 bg-primary text-white text-[10px] font-bold rounded-full h-[18px] w-[18px] flex items-center justify-center border-2 border-white dark:border-gray-800 transition-transform duration-300"><?= $totalCartItems ?></span>
                </a>
                <button class="w-10 h-10 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-800 rounded-full transition-all" onclick="toggleTheme()">
                    <span class="material-icons-round dark:hidden text-2xl">dark_mode</span>
                    <span class="material-icons-round hidden dark:block text-yellow-400 text-2xl">light_mode</span>
                </button>
                
                <div class="relative group flex items-center">
                    <a href="<?= $isAdmin ? '../admin/dashboard.php' : '../profile/account.php' ?>" class="block w-10 h-10 rounded-full bg-gradient-to-tr <?= $isAdmin ? 'from-purple-400 to-indigo-400' : 'from-pink-300 to-purple-300' ?> p-0.5 shadow-sm hover:shadow-md hover:scale-105 transition-all cursor-pointer">
                        <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-full h-full">
                            <img alt="Profile" class="w-full h-full rounded-full object-cover" src="<?= htmlspecialchars($profileImage) ?>"/>
                        </div>
                    </a>
                    
                    <?php if($isLoggedIn): ?>
                    <div class="absolute right-0 hidden pt-4 top-full w-[320px] z-50 group-hover:block cursor-default">
                        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-[0_10px_40px_-10px_rgba(236,45,136,0.2)] border border-pink-100 dark:border-gray-700 overflow-hidden p-5 relative">
                            <div class="text-center mb-4">
                                <span class="text-sm font-medium <?= $isAdmin ? 'text-purple-500 font-bold' : 'text-gray-500 dark:text-gray-400' ?>">
                                    <?= $isAdmin ? 'Administrator Mode' : htmlspecialchars($userData['u_email'] ?? '') ?>
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
                                <h3 class="text-[22px] font-bold text-gray-800 dark:text-white">สวัสดี คุณ <?= htmlspecialchars($userData['u_username'] ?? 'User') ?></h3>
                            </div>
                            <div class="flex flex-col gap-3 mt-2">
                                <?php if($isAdmin): ?>
                                    <a href="../admin/dashboard.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-purple-500 hover:bg-purple-500 hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-purple-500">
                                        <span class="material-icons-round text-[20px]">admin_panel_settings</span> สำหรับ Admin
                                    </a>
                                <?php else: ?>
                                    <a href="../profile/account.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-primary hover:bg-primary hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-primary">
                                        จัดการบัญชี
                                    </a>
                                <?php endif; ?>
                                <a href="../auth/logout.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-red-500 hover:bg-red-500 hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-red-500">
                                    <span class="material-icons-round text-[20px]">logout</span> ออกจากระบบ
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
    <nav class="flex items-center justify-start mb-10 text-sm text-gray-500 dark:text-gray-400">
        <a class="hover:text-primary transition-colors" href="../home.php">หน้าหลัก</a>
        <span class="mx-2 material-icons-round text-[16px]">chevron_right</span>
        <a class="hover:text-primary transition-colors" href="products.php">สกินแคร์</a>
        <span class="mx-2 material-icons-round text-[16px]">chevron_right</span>
        <span class="text-primary font-bold truncate max-w-[200px]"><?= htmlspecialchars($product['p_name']) ?></span>
    </nav>

    <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] p-6 lg:p-10 shadow-soft border border-pink-50 dark:border-gray-700 mb-16">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-start">
            
            <div class="flex flex-col items-center relative">
                <div class="absolute top-4 right-4 z-10 pointer-events-none">
                    <span class="bg-primary text-white text-xs font-bold px-4 py-1.5 rounded-full shadow-md tracking-wide uppercase">ขายดี</span>
                </div>
                
                <div class="w-full max-w-[500px] aspect-square rounded-[2rem] overflow-hidden bg-pink-50 dark:bg-gray-700 flex items-center justify-center shadow-inner border border-gray-100 dark:border-gray-600 cursor-zoom-in" onclick="openLightbox()">
                    <img id="mainImage" alt="<?= htmlspecialchars($product['p_name']) ?>" class="w-full h-full object-cover transform hover:scale-105 transition duration-500" src="<?= htmlspecialchars($images[0]) ?>"/>
                </div>
                
                <?php if (count($images) > 1): ?>
                <div class="relative w-full max-w-[500px] mt-6">
                    <button onclick="scrollThumbs(-150)" class="absolute left-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 flex items-center justify-center bg-white/90 dark:bg-gray-800/90 rounded-full shadow border border-gray-100 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:text-primary transition-colors backdrop-blur-sm">
                        <span class="material-icons-round text-[20px]">chevron_left</span>
                    </button>
                    
                    <div id="thumb-container" class="flex gap-4 overflow-x-auto hide-scrollbar pb-2 px-10 scroll-smooth">
                        <?php foreach ($images as $index => $img): ?>
                            <button type="button" onclick="changeMainImage(this, '<?= htmlspecialchars($img) ?>', <?= $index ?>)" class="thumbnail-btn w-20 h-20 sm:w-24 sm:h-24 flex-shrink-0 rounded-[1.2rem] border-[3px] <?= $index == 0 ? 'border-primary shadow-md' : 'border-transparent hover:border-pink-300 opacity-60 hover:opacity-100' ?> overflow-hidden bg-white transition-all duration-300 p-0.5">
                                <img alt="Thumbnail" class="w-full h-full object-cover rounded-xl" src="<?= htmlspecialchars($img) ?>"/>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <button onclick="scrollThumbs(150)" class="absolute right-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 flex items-center justify-center bg-white/90 dark:bg-gray-800/90 rounded-full shadow border border-gray-100 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:text-primary transition-colors backdrop-blur-sm">
                        <span class="material-icons-round text-[20px]">chevron_right</span>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <div class="flex flex-col h-full py-2">
                
                <div class="mb-6">
                    <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 dark:text-white leading-tight mb-2">
                        <?= htmlspecialchars($product['p_name']) ?>
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400 text-base mb-3 flex items-center gap-2">
                        <span class="material-icons-round text-[18px] text-pink-400">category</span>
                        <?= htmlspecialchars($product['p_category'] ?? 'สินค้าทั่วไป') ?> | SKU: <?= htmlspecialchars($product['p_sku'] ?: '-') ?>
                    </p>
                    </div>

                <div class="flex items-end gap-3 mb-8 pb-6 border-b border-gray-100 dark:border-gray-700">
                    <span class="text-4xl sm:text-5xl font-extrabold text-primary tracking-tight">฿<?= number_format($product['p_price']) ?></span>
                </div>

                <div class="bg-pink-50/50 dark:bg-gray-700/50 rounded-2xl p-5 mb-8 border border-pink-100 dark:border-gray-600">
                    <ul class="space-y-3">
                        <li class="flex items-start gap-3">
                            <span class="material-icons-round text-green-500 bg-white dark:bg-gray-800 rounded-full text-[18px] p-0.5 shadow-sm mt-0.5">check</span>
                            <span class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed">สินค้าแท้ 100% จากแบรนด์ Lumina Beauty</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="material-icons-round text-green-500 bg-white dark:bg-gray-800 rounded-full text-[18px] p-0.5 shadow-sm mt-0.5">check</span>
                            <span class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed">จัดส่งฟรี เมื่อสั่งซื้อครบ 1,000 บาทขึ้นไป</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="material-icons-round text-green-500 bg-white dark:bg-gray-800 rounded-full text-[18px] p-0.5 shadow-sm mt-0.5">check</span>
                            <span class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed">สต็อกคงเหลือ: <?= $product['p_stock'] > 0 ? '<span class="text-green-600 font-bold">พร้อมส่ง (' . $product['p_stock'] . ' ชิ้น)</span>' : '<span class="text-red-500 font-bold">สินค้าหมด</span>' ?></span>
                        </li>
                    </ul>
                </div>

                <form id="productForm" onsubmit="addToCartAjax(event)" class="mt-auto">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="p_id" value="<?= $p_id ?>">
                    
                    <?php if(count($colors) > 0): ?>
                    <div class="mb-8">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-gray-800 dark:text-gray-200 font-bold">ตัวเลือกสี:</span>
                            <span id="selectedColorNameText" class="text-primary font-medium text-sm"><?= htmlspecialchars($colors[0]['color_name']) ?></span>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <?php foreach($colors as $index => $color): ?>
                                <button type="button"
                                        onclick="selectColor(this, '<?= htmlspecialchars($color['color_name']) ?>')"
                                        class="color-swatch relative w-10 h-10 rounded-full border-2 border-white dark:border-gray-800 focus:outline-none flex items-center justify-center transition-all duration-200 <?= $index == 0 ? 'ring-2 ring-primary scale-110 shadow-md' : 'ring-1 ring-gray-200 dark:ring-gray-600 hover:scale-110' ?>"
                                        style="background-color: <?= htmlspecialchars($color['color_hex']) ?>;"
                                        title="<?= htmlspecialchars($color['color_name']) ?>">
                                    <span class="material-icons-round text-white text-[18px] drop-shadow-md <?= $index == 0 ? 'block' : 'hidden' ?>" style="text-shadow: 0px 1px 2px rgba(0,0,0,0.5);">check</span>
                                </button>
                            <?php endforeach; ?>
                            <input type="hidden" name="selected_color" id="selectedColorInput" value="<?= count($colors)>0 ? htmlspecialchars($colors[0]['color_name']) : '' ?>">
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="flex flex-col sm:flex-row items-center gap-4">
                        <div class="flex items-center bg-gray-50 dark:bg-gray-700 rounded-full p-1.5 border border-gray-200 dark:border-gray-600 w-full sm:w-auto justify-between sm:justify-start">
                            <span class="text-gray-500 font-medium ml-3 mr-2 sm:hidden">จำนวน</span>
                            <div class="flex items-center">
                                <button type="button" onclick="adjustQty(-1)" class="w-10 h-10 rounded-full bg-white dark:bg-gray-600 shadow-sm flex items-center justify-center text-gray-600 dark:text-white hover:text-primary hover:bg-pink-50 transition-colors">
                                    <span class="material-icons-round text-[20px]">remove</span>
                                </button>
                                <input type="number" name="qty" id="qtyInput" value="1" min="1" max="<?= $product['p_stock'] > 0 ? $product['p_stock'] : 1 ?>" class="w-14 text-center font-bold text-gray-900 dark:text-white bg-transparent border-none outline-none focus:ring-0 p-0 text-lg pointer-events-none" readonly>
                                <button type="button" onclick="adjustQty(1)" class="w-10 h-10 rounded-full bg-white dark:bg-gray-600 shadow-sm flex items-center justify-center text-gray-600 dark:text-white hover:text-primary hover:bg-pink-50 transition-colors">
                                    <span class="material-icons-round text-[20px]">add</span>
                                </button>
                            </div>
                        </div>

                        <?php if($product['p_stock'] > 0): ?>
                        <button type="submit" class="flex-1 w-full bg-primary hover:bg-pink-600 text-white font-bold py-4 px-8 rounded-full transition-all duration-300 transform hover:-translate-y-1 shadow-[0_8px_25px_-8px_rgba(244,63,133,0.6)] flex items-center justify-center gap-2 text-lg relative overflow-hidden">
                            <span class="material-icons-round">add_shopping_cart</span> เพิ่มลงตะกร้า
                        </button>
                        <?php else: ?>
                        <button type="button" disabled class="flex-1 w-full bg-gray-300 text-gray-500 font-bold py-4 px-8 rounded-full cursor-not-allowed flex items-center justify-center gap-2 text-lg">
                            สินค้าหมดชั่วคราว
                        </button>
                        <?php endif; ?>
                        
                        <button type="button" onclick="addToFavAjax()" class="w-[60px] h-[60px] sm:w-[60px] sm:flex-none flex-shrink-0 rounded-full border-2 border-gray-200 dark:border-gray-600 text-gray-400 hover:text-primary hover:border-primary hover:bg-pink-50 transition-all duration-300 flex items-center justify-center group bg-white dark:bg-gray-800 shadow-sm" title="เพิ่มลงสิ่งที่ถูกใจ">
                            <span class="material-icons-round text-[26px] group-hover:scale-110 transition-transform">favorite_border</span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <div class="mt-10 mb-20 max-w-4xl mx-auto">
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-8 sm:p-12 shadow-sm border border-gray-100 dark:border-gray-700">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">
                <span class="material-icons-round text-primary">description</span> รายละเอียดสินค้า
            </h3>
            <div class="text-gray-600 dark:text-gray-300 leading-relaxed space-y-4 whitespace-pre-line text-lg">
                <?= htmlspecialchars($product['p_detail'] ?: 'ไม่มีข้อมูลรายละเอียดสินค้า') ?>
            </div>
        </div>
    </div>

    <?php if (count($recommended) > 0): ?>
    <div class="mt-16 border-t border-gray-100 dark:border-gray-800 pt-16">
        <div class="flex justify-between items-end mb-10">
            <div>
                <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">สินค้าที่คุณอาจชอบ</h2>
                <p class="text-gray-500 mt-2 text-sm">เติมเต็มขั้นตอนการดูแลผิวของคุณให้สมบูรณ์</p>
            </div>
            <a class="text-primary hover:text-pink-600 font-bold flex items-center gap-1 transition-colors text-sm" href="products.php">
                ดูทั้งหมด <span class="material-icons-round text-[18px]">arrow_forward</span>
            </a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6 pb-8">
            <?php foreach($recommended as $rec): 
                $recImg = (!empty($rec['p_image']) && file_exists("../uploads/products/" . $rec['p_image'])) 
                            ? "../uploads/products/" . $rec['p_image'] 
                            : "https://via.placeholder.com/400x400.png?text=No+Image";
            ?>
            <a href="productdetail.php?id=<?= $rec['p_id'] ?>" class="bg-white dark:bg-surface-dark rounded-[2rem] p-4 shadow-sm hover:shadow-glow transition-all duration-300 group border border-gray-100 dark:border-gray-700 block relative hover:-translate-y-1">
                <div class="absolute top-6 right-6 z-10 opacity-0 group-hover:opacity-100 transition-opacity">
                    <div class="w-8 h-8 rounded-full bg-white/90 backdrop-blur-sm text-gray-400 flex items-center justify-center shadow-sm hover:text-primary">
                        <span class="material-icons-round text-[18px]">favorite_border</span>
                    </div>
                </div>
                <div class="relative rounded-[1.5rem] overflow-hidden aspect-square mb-5 bg-gray-50 dark:bg-gray-800 flex items-center justify-center">
                    <img alt="<?= htmlspecialchars($rec['p_name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" src="<?= $recImg ?>"/>
                </div>
                <div class="px-1">
                    <h3 class="font-bold text-gray-800 dark:text-white text-[15px] mb-1 line-clamp-1 group-hover:text-primary transition-colors"><?= htmlspecialchars($rec['p_name']) ?></h3>
                    <p class="text-[13px] text-gray-500 mb-3 line-clamp-1"><?= htmlspecialchars($rec['p_detail']) ?></p>
                    <div class="flex justify-between items-center mt-auto">
                        <span class="text-primary font-extrabold text-lg">฿<?= number_format($rec['p_price']) ?></span>
                        <div class="w-8 h-8 rounded-full bg-pink-50 text-primary flex items-center justify-center group-hover:bg-primary group-hover:text-white transition-colors">
                            <span class="material-icons-round text-[16px]">add</span>
                        </div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</main>

<footer class="bg-white dark:bg-surface-dark border-t border-primary/10 mt-12 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="flex items-center justify-center gap-2 mb-6">
            <span class="material-icons-round text-primary text-3xl">spa</span>
            <span class="font-display font-bold text-xl tracking-tight text-gray-900 dark:text-white">Lumina<span class="text-primary">Beauty</span></span>
        </div>
        <p class="text-gray-500 dark:text-gray-400 mb-8 max-w-md mx-auto">
            ความงามที่เปล่งประกายจากภายในสู่ภายนอก ผลิตภัณฑ์ดูแลผิวที่คัดสรรมาเพื่อคุณโดยเฉพาะ
        </p>
        <p class="text-xs text-gray-400">© 2026 Lumina Beauty. All rights reserved.</p>
    </div>
</footer>

<div id="lightbox" class="fixed inset-0 z-[100] hidden bg-black/95 backdrop-blur-sm flex items-center justify-center opacity-0 transition-opacity duration-300">
    <button onclick="closeLightbox()" class="absolute top-4 right-4 sm:top-8 sm:right-8 w-12 h-12 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full text-white transition-colors z-50">
        <span class="material-icons-round text-3xl">close</span>
    </button>
    
    <?php if(count($images) > 1): ?>
    <button onclick="changeLightboxImg(-1)" class="absolute left-4 sm:left-10 top-1/2 -translate-y-1/2 w-14 h-14 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full text-white transition-colors z-50">
        <span class="material-icons-round text-4xl">chevron_left</span>
    </button>
    <button onclick="changeLightboxImg(1)" class="absolute right-4 sm:right-10 top-1/2 -translate-y-1/2 w-14 h-14 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full text-white transition-colors z-50">
        <span class="material-icons-round text-4xl">chevron_right</span>
    </button>
    <?php endif; ?>
    
    <div class="relative overflow-hidden w-full max-w-5xl h-[85vh] flex items-center justify-center px-4">
        <img id="lightbox-img" src="" class="max-w-full max-h-full object-contain transition-transform duration-300 select-none" style="transform: scale(1);">
    </div>
    
    <div class="absolute bottom-8 flex gap-3 bg-white/10 px-6 py-3 rounded-full backdrop-blur-md">
        <button onclick="zoomLightbox(-0.25)" class="text-white hover:text-primary transition-colors flex items-center justify-center w-10 h-10"><span class="material-icons-round text-3xl">zoom_out</span></button>
        <button onclick="zoomLightbox(0)" class="text-white hover:text-primary transition-colors flex items-center justify-center w-10 h-10"><span class="material-icons-round text-3xl">search</span></button>
        <button onclick="zoomLightbox(0.25)" class="text-white hover:text-primary transition-colors flex items-center justify-center w-10 h-10"><span class="material-icons-round text-3xl">zoom_in</span></button>
    </div>
</div>

<script>
    const productImages = <?= json_encode($images) ?>;
    let currentImageIndex = 0;
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

    if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
    function toggleTheme() {
        const htmlEl = document.documentElement; htmlEl.classList.toggle('dark');
        localStorage.setItem('theme', htmlEl.classList.contains('dark') ? 'dark' : 'light');
    }

    // ฟังก์ชันเลื่อนรูปรองซ้ายขวา
    function scrollThumbs(amount) {
        const container = document.getElementById('thumb-container');
        if (container) {
            container.scrollBy({ left: amount, behavior: 'smooth' });
        }
    }

    // ฟังก์ชันปรับจำนวน
    function adjustQty(amount) {
        const input = document.getElementById('qtyInput');
        const maxStock = parseInt(input.getAttribute('max')) || 99;
        let currentVal = parseInt(input.value) || 1;
        let newVal = currentVal + amount;
        if (newVal >= 1 && newVal <= maxStock) input.value = newVal;
    }

    // ฟังก์ชันเปลี่ยนรูปหลัก
    function changeMainImage(clickedBtn, imgSrc, index) {
        document.getElementById('mainImage').src = imgSrc;
        currentImageIndex = index;
        
        const btns = document.querySelectorAll('.thumbnail-btn');
        btns.forEach(btn => {
            btn.classList.remove('border-primary', 'shadow-md', 'opacity-100');
            btn.classList.add('border-transparent', 'opacity-60');
        });
        clickedBtn.classList.remove('border-transparent', 'opacity-60');
        clickedBtn.classList.add('border-primary', 'shadow-md', 'opacity-100');
    }

    // ฟังก์ชันเลือกสี
    function selectColor(btn, colorName) {
        document.getElementById('selectedColorInput').value = colorName;
        document.getElementById('selectedColorNameText').textContent = colorName;
        const swatches = document.querySelectorAll('.color-swatch');
        swatches.forEach(swatch => {
            swatch.classList.remove('ring-2', 'ring-primary', 'scale-110', 'shadow-md');
            swatch.classList.add('ring-1', 'ring-gray-200', 'dark:ring-gray-600', 'hover:scale-110');
            swatch.querySelector('.material-icons-round').classList.replace('block', 'hidden');
        });
        btn.classList.remove('ring-1', 'ring-gray-200', 'dark:ring-gray-600', 'hover:scale-110');
        btn.classList.add('ring-2', 'ring-primary', 'scale-110', 'shadow-md');
        btn.querySelector('.material-icons-round').classList.replace('hidden', 'block');
    }

    // 🟢 ระบบ Lightbox 🟢
    let currentZoom = 1;
    const lb = document.getElementById('lightbox');
    const lbImg = document.getElementById('lightbox-img');

    function openLightbox() {
        lbImg.src = productImages[currentImageIndex];
        currentZoom = 1;
        lbImg.style.transform = `scale(${currentZoom})`;
        lb.classList.remove('hidden');
        lb.classList.add('flex');
        setTimeout(() => lb.classList.remove('opacity-0'), 10);
    }

    function closeLightbox() {
        lb.classList.add('opacity-0');
        setTimeout(() => { lb.classList.add('hidden'); lb.classList.remove('flex'); }, 300);
    }

    function changeLightboxImg(dir) {
        currentImageIndex += dir;
        if(currentImageIndex < 0) currentImageIndex = productImages.length - 1;
        if(currentImageIndex >= productImages.length) currentImageIndex = 0;
        
        lbImg.style.opacity = '0';
        setTimeout(() => {
            lbImg.src = productImages[currentImageIndex];
            currentZoom = 1;
            lbImg.style.transform = `scale(${currentZoom})`;
            lbImg.style.opacity = '1';
            
            // Sync กับหน้าเว็บหลักด้วย
            const thumbs = document.querySelectorAll('.thumbnail-btn');
            if(thumbs.length > 0) changeMainImage(thumbs[currentImageIndex], productImages[currentImageIndex], currentImageIndex);
        }, 200);
    }

    function zoomLightbox(factor) {
        if(factor === 0) currentZoom = 1;
        else currentZoom += factor;
        
        if(currentZoom < 0.5) currentZoom = 0.5;
        if(currentZoom > 3) currentZoom = 3;
        lbImg.style.transform = `scale(${currentZoom})`;
    }

    // 🟢 แอนิเมชันรูปลอยเข้าตะกร้า/หัวใจ (AJAX) 🟢
    function flyToIcon(targetIconId) {
        const sourceImg = document.getElementById('mainImage');
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

        // คำนวณให้บินไปตรงกลางไอคอน
        setTimeout(() => {
            flyingImg.style.left = (targetRect.left + targetRect.width / 2 - 20) + 'px';
            flyingImg.style.top = (targetRect.top + targetRect.height / 2 - 20) + 'px';
            flyingImg.style.width = '40px';
            flyingImg.style.height = '40px';
            flyingImg.style.opacity = '0';
        }, 10);

        setTimeout(() => {
            flyingImg.remove();
            // เอฟเฟกต์เด้งที่ไอคอน
            targetIcon.classList.add('scale-125', 'text-primary');
            setTimeout(() => targetIcon.classList.remove('scale-125', 'text-primary'), 300);
        }, 800);
    }

    function addToCartAjax(e) {
        e.preventDefault();
        if(!isLoggedIn) {
            window.location.href = '../auth/login.php';
            return;
        }

        const form = document.getElementById('productForm');
        const formData = new FormData(form);
        const qty = parseInt(document.getElementById('qtyInput').value);

        fetch('cart.php', {
            method: 'POST',
            body: formData
        }).then(response => {
            if(response.ok) {
                // เล่นแอนิเมชัน
                flyToIcon('nav-cart-icon');
                
                // อัปเดตตัวเลขในตะกร้า
                setTimeout(() => {
                    const badge = document.getElementById('cart-badge');
                    let currentBadgeQty = parseInt(badge.innerText) || 0;
                    badge.innerText = currentBadgeQty + qty;
                }, 800);
            }
        });
    }

    function addToFavAjax() {
        if(!isLoggedIn) {
            window.location.href = '../auth/login.php';
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'add_fav');
        formData.append('p_id', '<?= $p_id ?>');

        fetch('favorites.php', {
            method: 'POST',
            body: formData
        }).then(response => {
            if(response.ok) {
                flyToIcon('nav-fav-icon');
                const heartIcon = document.querySelector('#nav-fav-icon span');
                setTimeout(() => {
                    heartIcon.innerText = 'favorite'; // เปลี่ยนเป็นหัวใจทึบ
                    heartIcon.classList.add('text-primary');
                }, 800);
            }
        });
    }
</script>
</body>
</html>