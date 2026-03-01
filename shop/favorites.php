<?php
session_start();
require_once '../config/connectdbuser.php';

// ==========================================
// 🔴 แก้ที่ 3: รับ Request AJAX สำหรับเพิ่ม/ลบ ถูกใจ (ไม่มีแจ้งเตือน / แบบเงียบๆ)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_fav') {
    if (!isset($_SESSION['u_id'])) exit;
    $u_id = $_SESSION['u_id'];
    $p_id = (int)$_POST['p_id'];

    // เช็คว่ามีอยู่ในตารางถูกใจแล้วหรือไม่
    $checkSql = "SELECT fav_id FROM favorites WHERE u_id = ? AND p_id = ?";
    if ($stmtCheck = mysqli_prepare($conn, $checkSql)) {
        mysqli_stmt_bind_param($stmtCheck, "ii", $u_id, $p_id);
        mysqli_stmt_execute($stmtCheck);
        $res = mysqli_stmt_get_result($stmtCheck);
        
        if (mysqli_num_rows($res) > 0) {
            // ถ้ามีแล้ว = ให้ลบออก (Unfavorite)
            $delSql = "DELETE FROM favorites WHERE u_id = ? AND p_id = ?";
            $stmtDel = mysqli_prepare($conn, $delSql);
            mysqli_stmt_bind_param($stmtDel, "ii", $u_id, $p_id);
            mysqli_stmt_execute($stmtDel);
        } else {
            // ถ้ายังไม่มี = ให้เพิ่ม (Favorite)
            $addSql = "INSERT INTO favorites (u_id, p_id) VALUES (?, ?)";
            $stmtAdd = mysqli_prepare($conn, $addSql);
            mysqli_stmt_bind_param($stmtAdd, "ii", $u_id, $p_id);
            mysqli_stmt_execute($stmtAdd);
        }
    }
    exit; // จบการทำงาน ไม่ต้องโหลดหน้าเว็บต่อ
}

// ==========================================
// การทำงานปกติของหน้า Favorites (โหลดหน้าเว็บ)
// ==========================================
$isLoggedIn = false;
$isAdmin = false;
$profileImage = "https://ui-avatars.com/api/?name=Guest&background=E5E7EB&color=9CA3AF"; 
$userData = ['u_username' => 'ผู้เยี่ยมชม', 'u_email' => 'กรุณาเข้าสู่ระบบ'];
$favoriteProducts = []; 
$totalCartItems = 0;

// 🟢 แก้ไข: เพิ่มการตรวจสอบ Session ของ Admin 🟢
if (isset($_SESSION['admin_id'])) {
    $isLoggedIn = true;
    $isAdmin = true;
    $userData['u_username'] = $_SESSION['admin_username'] ?? 'Admin';
    $userData['u_email'] = 'Administrator Mode';
    $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($userData['u_username']) . "&background=a855f7&color=fff";
    
} elseif (isset($_SESSION['u_id'])) {
    $isLoggedIn = true;
    $u_id = $_SESSION['u_id'];
    
    // ----------------------------------------------------
    // รับค่าลบสินค้าที่ถูกใจ (เมื่อกดลบในหน้านี้โดยตรง)
    // ----------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $p_id = isset($_POST['p_id']) ? (int)$_POST['p_id'] : 0;
        if ($p_id > 0 && $_POST['action'] === 'remove_fav') {
            $deleteFav = "DELETE FROM `favorites` WHERE u_id = ? AND p_id = ?";
            $stmtDelete = mysqli_prepare($conn, $deleteFav);
            mysqli_stmt_bind_param($stmtDelete, "ii", $u_id, $p_id);
            mysqli_stmt_execute($stmtDelete);
            mysqli_stmt_close($stmtDelete);
        }
        header("Location: favorites.php");
        exit();
    }

    // ----------------------------------------------------
    // ดึงข้อมูลผู้ใช้เพื่อเอารูปโปรไฟล์มาแสดงที่ Navbar
    // ----------------------------------------------------
    $sql = "SELECT a.u_username, a.u_email, u.u_image 
            FROM `account` a 
            LEFT JOIN `user` u ON a.u_id = u.u_id 
            WHERE a.u_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $u_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($accountData = mysqli_fetch_assoc($result)) {
            $userData = $accountData;
            
            // ใช้ Path สำหรับโฟลเดอร์ profile/uploads
            $physical_path = __DIR__ . "/../profile/uploads/" . $accountData['u_image'];
            if (!empty($accountData['u_image']) && file_exists($physical_path)) {
                $profileImage = "../profile/uploads/" . $accountData['u_image'];
            } else {
                $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($accountData['u_username'] ?? 'U') . "&background=F43F85&color=fff";
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // ----------------------------------------------------
    // ดึงข้อมูล "สินค้าที่ถูกใจ" ของผู้ใช้นี้
    // ----------------------------------------------------
    $sqlFav = "SELECT p.* FROM `favorites` f 
               JOIN `product` p ON f.p_id = p.p_id 
               WHERE f.u_id = ? ORDER BY f.created_at DESC";
    if ($stmtFav = mysqli_prepare($conn, $sqlFav)) {
        mysqli_stmt_bind_param($stmtFav, "i", $u_id);
        mysqli_stmt_execute($stmtFav);
        $resultFav = mysqli_stmt_get_result($stmtFav);
        while ($row = mysqli_fetch_assoc($resultFav)) {
            $favoriteProducts[] = $row;
        }
        mysqli_stmt_close($stmtFav);
    }
    
    // ----------------------------------------------------
    // ดึงจำนวนสินค้าในตะกร้า
    // ----------------------------------------------------
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
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>หน้าสิ่งที่ถูกใจ - Lumina Beauty</title>
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
              "card-light": "#FFFFFF",
              "card-dark": "#2D2635",
              "text-light": "#374151",
              "text-dark": "#E5E7EB",
              "pastel-pink": "#FFE4E6",
              "pastel-blue": "#E0E7FF",
              "pastel-purple": "#F3E8FF",
            },
            fontFamily: {
              display: ["Prompt", "sans-serif"],
              body: ["Prompt", "sans-serif"],
            },
            borderRadius: {
              DEFAULT: "1rem", 'xl': "1.5rem", '2xl': "2rem", '3xl': "3rem",
            },
            boxShadow: {
              'soft': '0 10px 40px -10px rgba(244, 63, 133, 0.15)',
              'glow': '0 0 20px rgba(244, 63, 133, 0.3)',
            },
            animation: {
                'float': 'float 6s ease-in-out infinite',
                'float-delayed': 'float 6s ease-in-out 3s infinite',
                'float-slow': 'float 8s ease-in-out 1s infinite',
                'float-fast': 'float 4s ease-in-out infinite',
                'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
            },
            keyframes: {
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-20px)' },
                }
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
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
        }
        .dark .glass-panel {
            background: rgba(45, 38, 53, 0.7);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #FBCFE8; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #F43F85; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark transition-colors duration-300 min-h-screen relative overflow-x-hidden">

<div class="fixed top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
    <div class="absolute top-[10%] left-[10%] w-[40%] h-[40%] rounded-full bg-pink-200 dark:bg-pink-900 blur-[100px] opacity-40 animate-pulse-slow"></div>
    <div class="absolute bottom-[10%] right-[10%] w-[40%] h-[40%] rounded-full bg-purple-200 dark:bg-purple-900 blur-[100px] opacity-30 animate-pulse-slow" style="animation-delay: 2s;"></div>

    <div class="absolute top-[12%] left-[5%] animate-float-slow opacity-70 dark:opacity-30 text-white dark:text-gray-600">
        <svg width="110" height="65" viewBox="0 0 100 60" fill="currentColor"><path d="M25,50 C11.19,50 0,38.81 0,25 C0,11.19 11.19,0 25,0 C30.68,0 35.91,1.88 40.16,5.09 C43.83,2.02 48.55,0 53.75,0 C66.18,0 76.25,10.07 76.25,22.5 C76.25,23.36 76.19,24.21 76.08,25.04 C80.64,22.45 85.92,21.25 91.25,21.25 C101.6,21.25 110,29.65 110,40 C110,50.35 101.6,58.75 91.25,58.75 L25,58.75 L25,50 Z"/></svg>
    </div>

    <div class="absolute bottom-[18%] right-[8%] animate-float opacity-50 dark:opacity-20 text-pink-200 dark:text-pink-900">
        <svg width="130" height="75" viewBox="0 0 120 70" fill="currentColor"><path d="M30,60 C13.43,60 0,46.57 0,30 C0,13.43 13.43,0 30,0 C36.82,0 43.09,2.26 48.19,6.11 C52.59,2.42 58.26,0 64.5,0 C79.41,0 91.5,12.09 91.5,27 C91.5,28.03 91.43,29.05 91.3,30.05 C96.77,26.94 103.1,25.5 109.5,25.5 C121.93,25.5 132,35.57 132,48 C132,60.43 121.93,70.5 109.5,70.5 L30,70.5 L30,60 Z"/></svg>
    </div>

    <div class="absolute top-[22%] right-[15%] animate-float-delayed opacity-50 dark:opacity-20 text-purple-200 dark:text-purple-900 hidden sm:block">
         <svg width="90" height="55" viewBox="0 0 100 60" fill="currentColor"><path d="M25,50 C11.19,50 0,38.81 0,25 C0,11.19 11.19,0 25,0 C30.68,0 35.91,1.88 40.16,5.09 C43.83,2.02 48.55,0 53.75,0 C66.18,0 76.25,10.07 76.25,22.5 C76.25,23.36 76.19,24.21 76.08,25.04 C80.64,22.45 85.92,21.25 91.25,21.25 C101.6,21.25 110,29.65 110,40 C110,50.35 101.6,58.75 91.25,58.75 L25,58.75 L25,50 Z"/></svg>
    </div>

    <div class="absolute top-32 right-20 text-pink-300 dark:text-pink-800 animate-float-delayed opacity-60">
        <span class="material-icons-round text-5xl">favorite</span>
    </div>
    <div class="absolute bottom-40 left-16 text-purple-300 dark:text-purple-800 animate-float-slow opacity-50" style="animation-delay: 1s;">
        <span class="material-icons-round text-6xl">favorite_border</span>
    </div>
</div>

<header class="sticky top-0 z-50 glass-panel shadow-sm px-6 py-4 mb-8 relative">
    <div class="w-full px-4 md:px-10 lg:px-16"> 
        <div class="flex justify-between items-center h-10 w-full">
            <a href="../home.php" class="flex items-center space-x-2 cursor-pointer hover:opacity-80 transition-opacity">
                <span class="material-icons-round text-primary text-4xl">spa</span>
                <span class="font-bold text-2xl tracking-tight text-primary font-display">Lumina</span>
            </a>

            <div class="flex items-center space-x-2 sm:space-x-4">
                
                <div class="hidden md:flex items-center relative mr-2">
                    <form action="products.php" method="GET">
                        <input type="text" name="search" placeholder="ค้นหาสินค้า..." class="pl-10 pr-4 py-2 bg-pink-50 dark:bg-gray-800 border-none rounded-full text-sm focus:ring-2 focus:ring-primary w-48 lg:w-64 transition-all placeholder-gray-400 dark:text-white outline-none">
                        <button type="submit" class="material-icons-round absolute left-3 top-2 text-gray-400 text-lg">search</button>
                    </form>
                </div>

                <a href="favorites.php" class="text-primary hover:text-pink-600 transition relative flex items-center justify-center group">
                    <span class="material-icons-round text-2xl transition-transform duration-300 group-hover:scale-110">favorite</span>
                </a>
                <a href="cart.php" class="relative w-10 h-10 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-800 rounded-full transition-all cursor-pointer">
                    <span class="material-icons-round text-2xl transition-transform duration-300">shopping_bag</span>
                    <span class="absolute -top-1.5 -right-2 bg-primary text-white text-[10px] font-bold rounded-full h-[18px] w-[18px] flex items-center justify-center border-2 border-white dark:border-gray-800 transition-transform duration-300"><?= $totalCartItems ?></span>
                </a>
                <button class="w-10 h-10 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-800 rounded-full transition-all" onclick="toggleTheme()">
                    <span class="material-icons-round dark:hidden text-2xl">dark_mode</span>
                    <span class="material-icons-round hidden dark:block text-yellow-400 text-2xl">light_mode</span>
                </button>
                
                <div class="relative group flex items-center">
                    <a href="<?= $isAdmin ? '../admin/dashboard.php' : '../profile/account.php' ?>" class="block w-10 h-10 rounded-full bg-gradient-to-tr <?= $isAdmin ? 'from-purple-400 to-indigo-400' : 'from-pink-300 to-purple-300' ?> p-0.5 shadow-sm hover:shadow-md hover:scale-105 transition-all cursor-pointer">
                        <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-full h-full">
                            <img alt="Profile" class="w-full h-full rounded-full object-cover" src="<?= htmlspecialchars($profileImage) ?>" onerror="this.src='https://ui-avatars.com/api/?name=User&background=ec2d88&color=fff'"/>
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

<main class="relative z-10 w-full min-h-[calc(100vh-80px)] pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        
        <div class="flex items-center justify-center mb-12 relative">
            <div class="text-center z-10">
                <div class="inline-flex items-center justify-center bg-card-light dark:bg-card-dark px-6 py-3 rounded-full shadow-soft mb-4 border border-pink-50 dark:border-gray-700">
                    <span class="material-icons-round text-primary mr-2">favorite</span>
                    <h1 class="text-2xl sm:text-3xl font-display font-bold text-gray-800 dark:text-white">สิ่งที่ถูกใจ</h1>
                </div>
                <?php if (!$isLoggedIn): ?>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">กรุณาเข้าสู่ระบบเพื่อดูสินค้าที่คุณชื่นชอบ</p>
                <?php else: ?>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">รายการสินค้าที่คุณชื่นชอบทั้งหมด <?= count($favoriteProducts) ?> รายการ</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$isLoggedIn): ?>
            <div class="bg-card-light dark:bg-card-dark rounded-3xl p-10 shadow-soft flex flex-col items-center justify-center text-center border border-transparent dark:border-gray-700 min-h-[350px] max-w-2xl mx-auto">
                <div class="w-24 h-24 bg-purple-50 dark:bg-gray-800 rounded-full flex items-center justify-center mb-4 text-accent opacity-80">
                    <span class="material-icons-round text-5xl">lock</span>
                </div>
                <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">กรุณาเข้าสู่ระบบ</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">เข้าสู่ระบบหรือสมัครสมาชิก เพื่อบันทึกและดูรายการสินค้าที่คุณชื่นชอบ</p>
                
                <div class="flex flex-col sm:flex-row gap-4 w-full justify-center max-w-sm">
                    <a href="../auth/login.php" class="w-full sm:w-1/2 bg-primary hover:bg-pink-600 text-white px-6 py-3 rounded-full font-medium shadow-md transition-all text-center">
                        เข้าสู่ระบบ
                    </a>
                    <a href="../auth/register.php" class="w-full sm:w-1/2 bg-pink-50 dark:bg-gray-800 text-primary dark:text-pink-400 hover:bg-pink-100 dark:hover:bg-gray-700 px-6 py-3 rounded-full font-medium transition-all text-center">
                        สมัครสมาชิก
                    </a>
                </div>
            </div>

        <?php else: ?>
            
            <?php if (count($favoriteProducts) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    
                    <?php foreach($favoriteProducts as $p): 
                        $p_id = $p['p_id'];
                        $p_name = $p['p_name'];
                        $p_desc = $p['p_detail'];
                        $p_price = number_format($p['p_price']);
                        
                        $p_image = (!empty($p['p_image']) && file_exists("../uploads/products/" . $p['p_image'])) 
                                    ? "../uploads/products/" . $p['p_image'] 
                                    : "https://via.placeholder.com/400x400.png?text=No+Image";
                        
                        $bg_classes = ['bg-pastel-blue/30', 'bg-pastel-pink/30', 'bg-pastel-purple/30'];
                        $random_bg = $bg_classes[array_rand($bg_classes)];
                    ?>
                        <div class="bg-card-light dark:bg-card-dark rounded-2xl p-4 shadow-soft hover:shadow-glow transition-all duration-300 group hover:-translate-y-2 relative flex flex-col border border-pink-50 dark:border-gray-700">
                            
                            <div class="w-full aspect-square <?= $random_bg ?> rounded-xl overflow-hidden mb-4 relative">
                                <form action="favorites.php" method="POST" class="absolute top-3 right-3 z-10">
                                    <input type="hidden" name="action" value="remove_fav">
                                    <input type="hidden" name="p_id" value="<?= htmlspecialchars($p_id) ?>">
                                    <button type="submit" class="text-primary hover:scale-110 transition-transform" title="ลบออกจากรายการที่ชอบ">
                                        <span class="material-icons-round text-2xl">favorite</span>
                                    </button>
                                </form>
                                <a href="productdetail.php?id=<?= $p_id ?>">
                                    <img alt="<?= htmlspecialchars($p_name) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="<?= htmlspecialchars($p_image) ?>"/>
                                </a>
                            </div>
                            
                            <div class="flex-1 flex flex-col">
                                <a href="productdetail.php?id=<?= $p_id ?>">
                                    <h3 class="text-lg font-display font-bold text-gray-800 dark:text-white mb-1 line-clamp-1 hover:text-primary transition-colors" title="<?= htmlspecialchars($p_name) ?>"><?= htmlspecialchars($p_name) ?></h3>
                                </a>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3 line-clamp-1"><?= htmlspecialchars($p_desc) ?></p>
                                <div class="mt-auto flex justify-between items-center">
                                    <span class="text-xl font-bold text-primary">฿<?= $p_price ?></span>
                                </div>
                                
                                <form action="cart.php" method="POST" class="mt-4">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="p_id" value="<?= htmlspecialchars($p_id) ?>">
                                    <button type="submit" class="w-full bg-pink-50 dark:bg-gray-800 text-primary dark:text-pink-400 hover:bg-primary hover:text-white py-2 rounded-xl font-medium transition-colors flex items-center justify-center gap-2">
                                        <span class="material-icons-round text-sm">shopping_cart</span>
                                        เพิ่มลงตะกร้า
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
            <?php else: ?>
                <div class="bg-card-light dark:bg-card-dark rounded-3xl p-16 shadow-soft text-center border border-transparent dark:border-gray-700 flex flex-col items-center justify-center min-h-[350px]">
                    <div class="w-24 h-24 bg-pink-50 dark:bg-gray-800 rounded-full flex items-center justify-center mb-6 text-primary opacity-80">
                        <span class="material-icons-round text-6xl">favorite_border</span>
                    </div>
                    <h3 class="text-2xl font-display font-bold text-gray-800 dark:text-white mb-2">ยังไม่มีสิ่งที่ถูกใจ</h3>
                    <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-6">คุณยังไม่ได้เพิ่มสินค้าใดๆ ลงในรายการสิ่งที่ถูกใจ ลองไปเลือกดูสินค้าที่หน้าแรกสิ!</p>
                    <a href="products.php" class="px-8 py-3 bg-primary text-white rounded-full font-bold hover:bg-pink-600 transition shadow-md">
                        ไปเลือกช้อปสินค้า
                    </a>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</main>

<footer class="bg-card-light dark:bg-card-dark py-10 border-t border-pink-50 dark:border-gray-800">
    <div class="max-w-7xl mx-auto px-4 text-center">
        <div class="flex justify-center items-center mb-6 opacity-80">
            <span class="text-primary material-icons-round text-2xl mr-2">spa</span>
            <span class="font-display font-bold text-xl text-gray-800 dark:text-white">Lumina Beauty</span>
        </div>
        <p class="text-gray-400 text-sm">© 2026 Lumina Beauty. All rights reserved.</p>
    </div>
</footer>

<script>
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }

    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        
        if (htmlEl.classList.contains('dark')) {
            localStorage.setItem('theme', 'dark');
        } else {
            localStorage.setItem('theme', 'light');
        }
    }
</script>

</body></html>