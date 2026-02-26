<?php
session_start();
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once '../config/connectdbuser.php';

// ==========================================
// 1. จัดการข้อมูลผู้ใช้และตะกร้า (สำหรับ Navbar)
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
    
    // นับจำนวนสินค้าในตะกร้า
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
}
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>โปรโมชั่น - Lumina Beauty</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
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
                        "DEFAULT": "1rem", "lg": "1.5rem", "xl": "2rem", "2xl": "3rem", "full": "9999px"
                    },
                    boxShadow: {
                        'soft': '0 10px 40px -10px rgba(244, 63, 133, 0.15)',
                        'glow': '0 0 20px rgba(244, 63, 133, 0.3)',
                    },
                    backgroundImage: {
                        'cloud-pattern': "url(\"data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23F43F85' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E\")",
                    }
                },
            },
        }
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

        .text-gradient {
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-image: linear-gradient(to right, #F43F85, #A78BFA);
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        .animate-float { animation: float 6s ease-in-out infinite; }
        .animate-float-delayed { animation: float 7s ease-in-out infinite; animation-delay: 1s; }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #FBCFE8; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #F43F85; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark overflow-x-hidden transition-colors duration-300">

<header class="sticky top-0 z-50 glass-panel shadow-sm px-6 py-4 mb-0 relative z-50">
    <div class="w-full px-6 md:px-10 lg:px-16"> 
        <div class="flex justify-between items-center h-20 w-full">
        <a href="../home.php" class="flex items-center space-x-2 cursor-pointer hover:opacity-80 transition-opacity">
            <span class="material-icons-round text-primary text-4xl">spa</span>
            <span class="font-bold text-2xl tracking-tight text-primary">Lumina</span>
        </a>

        <div class="hidden lg:flex gap-8 xl:gap-12 items-center justify-center flex-grow ml-20">
            <a class="group flex flex-col items-center justify-center transition" href="../shop/products.php">
                <span class="text-[18px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">สินค้า</span>
                <span class="text-[13px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Shop)</span>
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
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-pink-50 dark:hover:bg-gray-700 hover:text-primary transition">TONERPADS (โทนเนอร์แพด)</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-pink-50 dark:hover:bg-gray-700 hover:text-primary transition">BLUSH (บลัชออน)</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-pink-50 dark:hover:bg-gray-700 hover:text-primary transition">LIPS (ริมฝีปาก)</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-pink-50 dark:hover:bg-gray-700 hover:text-primary transition">SKIN (ผิว)</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-pink-50 dark:hover:bg-gray-700 hover:text-primary transition">EYES (ตา)</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-pink-50 dark:hover:bg-gray-700 hover:text-primary transition">ACCESSORIES (อุปกรณ์เสริม)</a>
                    </div>
                </div>
            </div>

            <a class="flex flex-col items-center justify-center cursor-default pointer-events-none">
                <span class="text-[18px] font-bold text-primary dark:text-primary leading-tight">โปรโมชั่น</span>
                <span class="text-[13px] text-primary/80 dark:text-primary/80">(Sale)</span>
            </a>
            <a class="group flex flex-col items-center justify-center transition" href="../auth/contact.php">
                <span class="text-[18px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">ติดต่อเรา</span>
                <span class="text-[13px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Contact)</span>
            </a>
        </div>

        <div class="flex items-center space-x-3 sm:space-x-5 text-gray-600 dark:text-gray-300">
            <a href="../shop/favorites.php" class="hover:text-primary transition relative flex items-center">
                <span class="material-icons-round text-2xl">favorite_border</span>
            </a>
            <a href="../shop/cart.php" class="hover:text-primary transition relative flex items-center">
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
                                    <?php 
                                        $displayName = $isLoggedIn ? $userData['u_username'] : 'ผ'; 
                                    ?>
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
                                <a href="logout.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-red-500 hover:bg-red-500 hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-red-500">
                                    <span class="material-icons-round text-[20px]">logout</span>
                                    ออกจากระบบ
                                </a>
                            <?php else: ?>
                                <a href="login.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-primary hover:bg-primary hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-primary">
                                    <span class="material-icons-round text-[20px]">login</span>
                                    เข้าสู่ระบบ
                                </a>
                                <a href="register.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-primary hover:bg-primary hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-primary">
                                    <span class="material-icons-round text-[20px]">person_add</span>
                                    สมัครสมาชิก
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
</div>
</nav>
</header>

<main class="min-h-screen">
    
    <section class="relative overflow-hidden bg-gradient-to-br from-pink-50 via-purple-50 to-pink-100 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 pb-12 pt-16 px-4 md:px-10 lg:px-40 rounded-b-[3rem] shadow-soft border-b border-pink-100 dark:border-gray-800">
        <div class="absolute top-10 left-10 text-white/40 dark:text-primary/10 animate-float text-6xl select-none pointer-events-none">☁️</div>
        <div class="absolute top-20 right-20 text-white/60 dark:text-purple-400/10 animate-float-delayed text-8xl select-none pointer-events-none">☁️</div>
        <div class="absolute bottom-10 left-1/4 text-pink-300/40 animate-float text-5xl select-none pointer-events-none">✨</div>
        
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                <div class="flex flex-col gap-6 text-center md:text-left order-2 md:order-1">
                    <span class="inline-block px-4 py-2 rounded-full bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm text-primary font-bold text-sm border border-pink-200 dark:border-gray-700 w-fit mx-auto md:mx-0 shadow-sm">
                        🎉 Super Sale Season
                    </span>
                    <h1 class="text-gray-800 dark:text-white text-4xl md:text-5xl lg:text-6xl font-black leading-tight tracking-tight">
                        โปรโมชั่น<br/>
                        <span class="text-gradient">สุดพิเศษ!</span>
                    </h1>
                    <p class="text-gray-600 dark:text-gray-300 text-lg font-medium leading-relaxed max-w-md mx-auto md:mx-0">
                        ลดสูงสุด 50% เฉพาะเดือนนี้เท่านั้น พร้อมของแถมสุดน่ารักเมื่อช้อปครบ 999 บาท
                    </p>
                    <div class="flex gap-4 justify-center md:justify-start pt-4">
                        <a href="products.php" class="bg-primary hover:bg-pink-600 text-white rounded-full px-8 py-4 font-bold text-lg shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex items-center gap-2">
                            <span class="material-icons-round">shopping_bag</span>
                            ช้อปเลย
                        </a>
                        <a href="#coupons" class="bg-white dark:bg-gray-800 hover:bg-pink-50 dark:hover:bg-gray-700 text-primary border-2 border-pink-100 dark:border-gray-700 rounded-full px-8 py-4 font-bold text-lg shadow-sm hover:shadow-md transition-all duration-300">
                            ดูคูปอง
                        </a>
                    </div>
                </div>
                <div class="relative flex justify-center order-1 md:order-2 h-[300px] md:h-[450px]">
                    <div class="relative w-full h-full flex items-center justify-center animate-float">
                        <img alt="3D cloud character" class="object-contain max-h-full drop-shadow-2xl rounded-3xl" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAul8eiVeAL79jsoTA_Z9sJZGPjixlSa3aTrOmGRrGXjXGObaYXFKfGIV7keEPJuuosIvYDrzdP_9Boyv3j7R1V3IxQHMjVjIuvY3LfjqWHjztN5y6a62U3wFlS6WfKEsHSw5xPbugaWgls9P_gC7hz7d-fYcES5dapcOpiPpFYKkmoq1dsMrof3nKWD4OJdKVQupVZODDqkNNEymcSI2utRJQ6Di6U5_Rts75fZX4eKBSHZrYMeeMpqr6NXT9AwnMG-9qQn4cjMdXw"/>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-12 px-4 md:px-10 lg:px-40 bg-transparent">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-wrap justify-center gap-6 md:gap-10">
                <button class="group flex flex-col items-center gap-3 min-w-[100px]">
                    <div class="w-16 h-16 md:w-20 md:h-20 rounded-full bg-pink-100 dark:bg-gray-800 group-hover:bg-primary transition-colors flex items-center justify-center text-primary group-hover:text-white shadow-sm border border-transparent dark:border-gray-700">
                        <span class="material-icons-round text-3xl md:text-4xl">percent</span>
                    </div>
                    <span class="font-bold text-gray-800 dark:text-gray-200 group-hover:text-primary transition-colors">ลดราคา</span>
                </button>
                <button class="group flex flex-col items-center gap-3 min-w-[100px]">
                    <div class="w-16 h-16 md:w-20 md:h-20 rounded-full bg-blue-100 dark:bg-gray-800 group-hover:bg-blue-500 transition-colors flex items-center justify-center text-blue-500 group-hover:text-white shadow-sm border border-transparent dark:border-gray-700">
                        <span class="material-icons-round text-3xl md:text-4xl">inventory_2</span>
                    </div>
                    <span class="font-bold text-gray-800 dark:text-gray-200 group-hover:text-blue-500 transition-colors">ซื้อ 1 แถม 1</span>
                </button>
                <button class="group flex flex-col items-center gap-3 min-w-[100px]">
                    <div class="w-16 h-16 md:w-20 md:h-20 rounded-full bg-purple-100 dark:bg-gray-800 group-hover:bg-purple-500 transition-colors flex items-center justify-center text-purple-600 group-hover:text-white shadow-sm border border-transparent dark:border-gray-700">
                        <span class="material-icons-round text-3xl md:text-4xl">redeem</span>
                    </div>
                    <span class="font-bold text-gray-800 dark:text-gray-200 group-hover:text-purple-500 transition-colors">ของแถม</span>
                </button>
                <button class="group flex flex-col items-center gap-3 min-w-[100px]">
                    <div class="w-16 h-16 md:w-20 md:h-20 rounded-full bg-green-100 dark:bg-gray-800 group-hover:bg-green-500 transition-colors flex items-center justify-center text-green-600 group-hover:text-white shadow-sm border border-transparent dark:border-gray-700">
                        <span class="material-icons-round text-3xl md:text-4xl">local_shipping</span>
                    </div>
                    <span class="font-bold text-gray-800 dark:text-gray-200 group-hover:text-green-500 transition-colors">ส่งฟรี</span>
                </button>
            </div>
        </div>
    </section>

    <section class="py-16 px-4 md:px-10 lg:px-40 bg-gradient-to-r from-pink-50 to-orange-50 dark:from-gray-800 dark:to-gray-900 border-y border-pink-100 dark:border-gray-800">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row items-center justify-between mb-8 gap-4">
                <div class="flex items-center gap-4">
                    <div class="bg-primary text-white p-2 rounded-xl shadow-md">
                        <span class="material-icons-round text-3xl">bolt</span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-black text-gray-800 dark:text-white">Flash Sale</h2>
                    <div class="flex items-center gap-2 text-primary font-bold bg-white dark:bg-gray-800 px-4 py-1.5 rounded-full shadow-sm border border-pink-100 dark:border-gray-700">
                        <span>02</span>:<span>45</span>:<span>30</span>
                    </div>
                </div>
                <a href="shop/products.php" class="text-primary font-bold hover:text-pink-600 flex items-center gap-1 transition-colors">
                    ดูทั้งหมด <span class="material-icons-round text-[18px]">arrow_forward</span>
                </a>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                <div class="bg-white dark:bg-surface-dark rounded-3xl p-3 shadow-soft hover:shadow-glow transition-all duration-300 group relative border border-transparent dark:border-gray-700">
                    <div class="absolute top-3 left-3 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full z-10 shadow-sm">-40%</div>
                    <div class="relative aspect-square rounded-2xl overflow-hidden mb-3 bg-gray-50 dark:bg-gray-800 flex items-center justify-center">
                        <img alt="Serum" class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-500" src="https://lh3.googleusercontent.com/aida-public/AB6AXuB8YYXST9MXG8AFkxf-UBSRQDrVcACnev5QF5O2xqfKGrlfRSvfLdz43cwn5Dd38aCvg3s_VKIhctTjK5phQkG_tVdZOWuwm51L8nHjUjp2R5E68UbCj4QkVNJsrD_y4iqfRQKN3gCaU0ZEx667OV7vZNvi_I8cFZGKK2__rTD8mAEMRGkjshvla-pILSKIxe74tiOVLPTxhvJ05Lea9V05X4wDCfAadysss7vCVCySd9zSPuPGH2CESLN-ggNhDX6FPa6Ln59vK2HI"/>
                    </div>
                    <h3 class="font-bold text-gray-800 dark:text-white truncate mb-1 px-1">Lumina Glow Serum</h3>
                    <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2 mb-2 mx-1">
                        <div class="bg-primary h-2 rounded-full" style="width: 70%"></div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2 px-1">ขายแล้ว 125 ชิ้น</p>
                    <div class="flex items-end justify-between px-1">
                        <div class="flex flex-col">
                            <span class="text-xs text-gray-400 line-through">฿890</span>
                            <span class="text-lg font-bold text-primary">฿534</span>
                        </div>
                        <button class="bg-primary text-white w-8 h-8 rounded-full flex items-center justify-center shadow-md hover:bg-pink-600 transition-colors">
                            <span class="material-icons-round text-sm">add_shopping_cart</span>
                        </button>
                    </div>
                </div>

                <div class="bg-white dark:bg-surface-dark rounded-3xl p-3 shadow-soft hover:shadow-glow transition-all duration-300 group relative border border-transparent dark:border-gray-700">
                    <div class="absolute top-3 left-3 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full z-10 shadow-sm">-50%</div>
                    <div class="relative aspect-square rounded-2xl overflow-hidden mb-3 bg-gray-50 dark:bg-gray-800 flex items-center justify-center">
                        <img alt="Cream" class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-500" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDyXUiF5Pxk0vNmqEICdUZovaUgFXsVLC2FqJ-_KO5SQJoPyUwKW4zhgBiaj7V2YaQGL5j_x4jhKB8ztBDB0ilIRWDGKha37wCNjImiehvJc28GEkR1knJJgXpR_CPyBy37Ah3mz36M6ys8aSL0xR38HmS4enHs3aMTC5Ea_yvLNlF99mSOxAwZcBOgT7-puFH941BHFVtWdNp-5sO8sf7nXTPzoUdxd8ppbdNe2iwgyH7Uy3hVN7APKSze9pNdEzrUt-1QrF3EXk_C"/>
                    </div>
                    <h3 class="font-bold text-gray-800 dark:text-white truncate mb-1 px-1">Rose Night Cream</h3>
                    <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2 mb-2 mx-1">
                        <div class="bg-primary h-2 rounded-full" style="width: 90%"></div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2 px-1">ขายแล้ว 850 ชิ้น</p>
                    <div class="flex items-end justify-between px-1">
                        <div class="flex flex-col">
                            <span class="text-xs text-gray-400 line-through">฿1,200</span>
                            <span class="text-lg font-bold text-primary">฿599</span>
                        </div>
                        <button class="bg-primary text-white w-8 h-8 rounded-full flex items-center justify-center shadow-md hover:bg-pink-600 transition-colors">
                            <span class="material-icons-round text-sm">add_shopping_cart</span>
                        </button>
                    </div>
                </div>
                
                </div>
        </div>
    </section>

    <section id="coupons" class="py-16 px-4 md:px-10 lg:px-40 bg-cloud-pattern">
        <div class="max-w-7xl mx-auto">
            <h2 class="text-2xl md:text-3xl font-black text-gray-800 dark:text-white mb-8 flex items-center gap-2">
                <span class="material-icons-round text-primary">confirmation_number</span>
                เก็บโค้ดส่วนลด
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="flex bg-white dark:bg-gray-800 rounded-2xl border-2 border-dashed border-primary overflow-hidden relative h-32 hover:-translate-y-1 transition-transform shadow-sm">
                    <div class="w-1/3 bg-primary flex flex-col items-center justify-center text-white p-2 border-r-2 border-dashed border-white dark:border-gray-800 relative">
                        <span class="text-xs font-medium">ลดทันที</span>
                        <span class="text-3xl font-black font-display mt-1">฿100</span>
                        <div class="absolute -top-3 -right-3 w-6 h-6 bg-background-light dark:bg-background-dark rounded-full"></div>
                        <div class="absolute -bottom-3 -right-3 w-6 h-6 bg-background-light dark:bg-background-dark rounded-full"></div>
                    </div>
                    <div class="flex-1 p-4 flex flex-col justify-between">
                        <div>
                            <h4 class="font-bold text-gray-800 dark:text-white">ส่วนลด 100 บาท</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">เมื่อช้อปครบ 500 บาท</p>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-[10px] text-gray-400">หมดอายุ 31 ส.ค.</span>
                            <button class="bg-pink-50 dark:bg-gray-700 hover:bg-primary hover:text-white text-primary text-xs font-bold px-4 py-2 rounded-full transition-colors">
                                เก็บโค้ด
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex bg-white dark:bg-gray-800 rounded-2xl border-2 border-dashed border-purple-500 overflow-hidden relative h-32 hover:-translate-y-1 transition-transform shadow-sm">
                    <div class="w-1/3 bg-purple-500 flex flex-col items-center justify-center text-white p-2 border-r-2 border-dashed border-white dark:border-gray-800 relative">
                        <span class="material-icons-round text-4xl">local_shipping</span>
                        <span class="text-sm font-bold mt-1">ส่งฟรี</span>
                        <div class="absolute -top-3 -right-3 w-6 h-6 bg-background-light dark:bg-background-dark rounded-full"></div>
                        <div class="absolute -bottom-3 -right-3 w-6 h-6 bg-background-light dark:bg-background-dark rounded-full"></div>
                    </div>
                    <div class="flex-1 p-4 flex flex-col justify-between">
                        <div>
                            <h4 class="font-bold text-gray-800 dark:text-white">ฟรีค่าจัดส่ง</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">ไม่มีขั้นต่ำ</p>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-[10px] text-gray-400">ใช้ได้ครั้งเดียว</span>
                            <button class="bg-purple-50 dark:bg-gray-700 hover:bg-purple-500 hover:text-white text-purple-500 text-xs font-bold px-4 py-2 rounded-full transition-colors">
                                เก็บโค้ด
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex bg-white dark:bg-gray-800 rounded-2xl border-2 border-dashed border-blue-500 overflow-hidden relative h-32 hover:-translate-y-1 transition-transform shadow-sm">
                    <div class="w-1/3 bg-blue-500 flex flex-col items-center justify-center text-white p-2 border-r-2 border-dashed border-white dark:border-gray-800 relative">
                        <span class="text-xs font-medium">ส่วนลด</span>
                        <span class="text-3xl font-black font-display mt-1">15%</span>
                        <div class="absolute -top-3 -right-3 w-6 h-6 bg-background-light dark:bg-background-dark rounded-full"></div>
                        <div class="absolute -bottom-3 -right-3 w-6 h-6 bg-background-light dark:bg-background-dark rounded-full"></div>
                    </div>
                    <div class="flex-1 p-4 flex flex-col justify-between">
                        <div>
                            <h4 class="font-bold text-gray-800 dark:text-white">ลูกค้าใหม่</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">ลดสูงสุด 200 บาท</p>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-[10px] text-gray-400">เฉพาะสั่งซื้อครั้งแรก</span>
                            <button class="bg-blue-50 dark:bg-gray-700 hover:bg-blue-500 hover:text-white text-blue-500 text-xs font-bold px-4 py-2 rounded-full transition-colors">
                                เก็บโค้ด
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<footer class="bg-white dark:bg-surface-dark py-10 border-t border-pink-50 dark:border-gray-800">
    <div class="max-w-7xl mx-auto px-4 md:px-10 text-center">
        <div class="flex items-center justify-center gap-2 mb-4 opacity-80">
            <span class="material-icons-round text-primary text-3xl">spa</span>
            <span class="font-bold font-display text-2xl text-gray-800 dark:text-white">Lumina Beauty</span>
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400">© 2026 Lumina Beauty. All rights reserved.</p>
    </div>
</footer>

<script>
    // สลับ Dark Mode
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }
    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        localStorage.setItem('theme', htmlEl.classList.contains('dark') ? 'dark' : 'light');
    }
</script>
</body></html>