<?php
session_start();
require_once '../config/connectdbuser.php';

// ตรวจสอบ Login
if (!isset($_SESSION['u_id'])) {
    header("Location: ../login.php");
    exit();
}

$u_id = $_SESSION['u_id'];

// ==========================================
// ส่วนจัดการอัปโหลด (ใช้ Session + SweetAlert2)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_image'])) {
    
    $file = $_FILES['profile_image'];

    // 1. เช็คว่ามี Error จากการอัปโหลดหรือไม่
    if ($file['error'] != 0) {
        $errorCode = $file['error'];
        $msg = "เกิดข้อผิดพลาดในการอัปโหลด (Code: $errorCode)";
        if ($errorCode == 1 || $errorCode == 2) $msg = "ไฟล์มีขนาดใหญ่เกินไป";

        $_SESSION['swal_alert'] = [
            'icon' => 'error',
            'title' => 'อัปโหลดไม่สำเร็จ',
            'text' => $msg
        ];
    } else {
        // 2. เตรียม Path และตรวจสอบนามสกุล
        $target_dir = __DIR__ . "/uploads/";
        
        // สร้างโฟลเดอร์ถ้าไม่มี
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_types)) {
            
            // ตั้งชื่อไฟล์ใหม่
            $new_filename = "profile_" . $u_id . "_" . time() . "." . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            // 3. ย้ายไฟล์
            if (move_uploaded_file($file["tmp_name"], $target_file)) {
                
                // ลบรูปเก่า
                $findOldSql = "SELECT u_image FROM `user` WHERE u_id = '$u_id'";
                $resOld = mysqli_query($conn, $findOldSql);
                if ($rowOld = mysqli_fetch_assoc($resOld)) {
                    if (!empty($rowOld['u_image']) && file_exists($target_dir . $rowOld['u_image'])) {
                        unlink($target_dir . $rowOld['u_image']);
                    }
                }

                // อัปเดต Database
                $updateSql = "INSERT INTO `user` (u_id, u_image) VALUES (?, ?) ON DUPLICATE KEY UPDATE u_image = ?";
                if ($updateStmt = mysqli_prepare($conn, $updateSql)) {
                    mysqli_stmt_bind_param($updateStmt, "iss", $u_id, $new_filename, $new_filename);
                    if(mysqli_stmt_execute($updateStmt)){
                        $_SESSION['swal_alert'] = [
                            'icon' => 'success',
                            'title' => 'สำเร็จ!',
                            'text' => 'เปลี่ยนรูปโปรไฟล์เรียบร้อยแล้ว'
                        ];
                    } else {
                        $_SESSION['swal_alert'] = [
                            'icon' => 'error',
                            'title' => 'Database Error',
                            'text' => 'บันทึกข้อมูลลงฐานข้อมูลไม่สำเร็จ'
                        ];
                    }
                    mysqli_stmt_close($updateStmt);
                }
            } else {
                $_SESSION['swal_alert'] = [
                    'icon' => 'error',
                    'title' => 'Permission Error',
                    'text' => 'ไม่สามารถเขียนไฟล์ได้ (กรุณาเช็คโฟลเดอร์ uploads)'
                ];
            }
        } else {
            $_SESSION['swal_alert'] = [
                'icon' => 'warning',
                'title' => 'ไฟล์ไม่ถูกต้อง',
                'text' => 'อนุญาตเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF, WEBP) เท่านั้น'
            ];
        }
    }

    // Redirect กลับเพื่อเคลียร์ค่า POST (แก้ปัญหา Refresh แล้วถามซ้ำ)
    header("Location: account.php");
    exit();
}

// ... ส่วนดึงข้อมูลเดิม ...
$userData = [];
$sql = "SELECT a.u_username, a.u_email, a.u_name, 
        u.u_phone, u.u_image, u.u_gender, u.u_birthdate, u.created_at, u.updated_at 
        FROM `account` a 
        LEFT JOIN `user` u ON a.u_id = u.u_id 
        WHERE a.u_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $u_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $userData = $row;
    }
    mysqli_stmt_close($stmt);
}

// ตั้งค่าตัวแปรเพื่อนำไปแสดงผล
$displayName = $userData['u_username'] ?? 'ผู้ใช้งาน';
$displayEmail = $userData['u_email'] ?? 'ไม่มีอีเมลในระบบ';
$fullName = !empty($userData['u_name']) ? $userData['u_name'] : 'ยังไม่ได้ระบุชื่อ';
$phone = !empty($userData['u_phone']) ? $userData['u_phone'] : '-';
$birthdate = !empty($userData['u_birthdate']) ? date('d/m/Y', strtotime($userData['u_birthdate'])) : '-';
$created_at = !empty($userData['created_at']) ? date('d/m/Y H:i', strtotime($userData['created_at'])) : '-';
$updated_at = !empty($userData['updated_at']) ? date('d/m/Y H:i', strtotime($userData['updated_at'])) : '-';

// แปลงเพศและ Hero Image
$gender = '-';
$randomSeed = uniqid(); 
$heroImage = "https://api.dicebear.com/9.x/notionists/svg?seed=" . $randomSeed . "&backgroundColor=transparent";

if (!empty($userData['u_gender'])) {
    if ($userData['u_gender'] == 'Male') {
        $gender = 'ชาย';
        $heroImage = "https://api.dicebear.com/9.x/notionists/svg?seed=Easton&backgroundColor=transparent";
    } elseif ($userData['u_gender'] == 'Female') {
        $gender = 'หญิง';
        $heroImage = "https://api.dicebear.com/9.x/notionists/svg?seed=Ryan&backgroundColor=transparent";
    } else {
        $gender = 'อื่นๆ';
        $heroImage = "https://api.dicebear.com/9.x/notionists/svg?seed=" . $randomSeed . "&backgroundColor=transparent";
    }
}

// ==========================================
// [แก้] จัดการ Path รูปโปรไฟล์ให้จบที่ PHP
// ==========================================
// เริ่มต้นด้วยรูป Default
$profileImage = "https://ui-avatars.com/api/?name=" . urlencode($displayName) . "&background=F43F85&color=fff&size=150";

// เช็คว่ามีรูปใน DB และไฟล์มีอยู่จริงไหม
if (!empty($userData['u_image']) && file_exists(__DIR__ . "/uploads/" . $userData['u_image'])) {
    // ถ้ามี ให้ใช้ Path เต็มที่ชี้ไปยังโฟลเดอร์ uploads ของ Server
    $profileImage = "/lumina/profile/uploads/" . $userData['u_image'];
}

$totalCartItems = 0;
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
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Lumina Beauty - หน้าจัดการบัญชี</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                        DEFAULT: "1.5rem",
                        'xl': '1rem',
                        '2xl': '1.5rem',
                        '3xl': '2rem',
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
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #FBCFE8; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #F43F85; }
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
    <span class="font-bold text-2xl tracking-tight text-primary">Lumina</span>
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
        <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-full h-full">
            <img alt="Profile" class="w-full h-full rounded-full object-cover" src="<?= htmlspecialchars($profileImage) ?>"/>
        </div>
    </a>
</div>
</div>
</div>
</div>
</div>
</nav>
<main class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
<div class="flex flex-col lg:flex-row gap-8">
<aside class="w-full lg:w-1/4">
<div class="bg-card-light dark:bg-card-dark rounded-3xl p-6 shadow-soft sticky top-28">
<div class="flex flex-col space-y-2">
<a class="flex items-center space-x-3 px-4 py-3 bg-pink-50 dark:bg-pink-900/20 text-primary font-medium rounded-2xl transition-all shadow-sm" href="#">
<span class="material-icons-round">person</span>
<span>ข้อมูลส่วนตัว</span>
</a>
<a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="manageaccount.php">
    <span class="material-icons-round text-4xl">manage_accounts</span>
    <span>รายละเอียดบัญชี</span>
</a>
<a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="payment.php">
    <span class="material-icons-round">credit_card</span>
    <span>วิธีการชำระเงิน</span>
</a>
<a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="history.php">
<span class="material-icons-round">history</span>
<span>ประวัติการสั่งซื้อ</span>
</a>
<a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="address.php">
<span class="material-icons-round">location_on</span>
<span>ที่อยู่จัดส่ง</span>
</a>
<a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="../shop/favorites.php">
<span class="material-icons-round">favorite</span>
<span>สิ่งที่ถูกใจ</span>
</a>
<div class="border-t border-gray-100 dark:border-gray-700 my-2 pt-2"></div>
<a class="flex items-center space-x-3 px-4 py-3 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-2xl transition-all" href="../auth/logout.php">
<span class="material-icons-round">logout</span>
<span>ออกจากระบบ</span>
</a>
</div>
</div>
</aside>
<section class="w-full lg:w-3/4 space-y-8">
<div class="bg-gradient-to-r from-pink-400 to-purple-400 rounded-3xl p-8 text-white relative overflow-hidden shadow-lg transform hover:scale-[1.01] transition duration-300">
<div class="relative z-10 flex flex-col md:flex-row items-center justify-between">
<div class="mb-4 md:mb-0 text-center md:text-left">
<h1 class="text-3xl font-bold mb-2">สวัสดีคุณลูกค้า! 👋</h1>
<p class="text-pink-100 opacity-90">ยินดีต้อนรับสู่หน้าจัดการบัญชีของคุณ วันนี้ผิวหน้าคุณดูสดใสมากเลยนะ!</p>
</div>
<div class="flex-shrink-0">
<div class="relative w-32 h-32 md:w-40 md:h-40">
<div class="absolute inset-0 bg-white/30 backdrop-blur-md rounded-full animate-bounce" style="animation-duration: 3s;"></div>
<img alt="Cute Character" class="relative z-10 w-full h-full object-contain filter drop-shadow-xl transform rotate-3 hover:rotate-6 transition" src="<?= htmlspecialchars($heroImage) ?>"/>
</div>
</div>
</div>
<div class="absolute -top-10 -left-10 w-40 h-40 bg-white opacity-10 rounded-full blur-2xl"></div>
<div class="absolute bottom-0 right-0 w-60 h-60 bg-purple-600 opacity-20 rounded-full blur-3xl"></div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-stretch">
<div class="bg-card-light dark:bg-card-dark rounded-3xl p-6 shadow-soft hover:shadow-lg transition duration-300 border border-transparent dark:border-gray-700 flex flex-col justify-between h-full">
<div class="flex justify-between items-center mb-6">
    <h2 class="text-xl font-bold flex items-center gap-2">
        <span class="material-icons-round text-primary">face</span>
        ข้อมูลส่วนตัว
    </h2>
    <a href="edit_profile.php" class="bg-pink-100 dark:bg-gray-700 text-primary dark:text-pink-400 hover:bg-primary hover:text-white px-4 py-1.5 rounded-full text-sm font-medium transition flex items-center gap-1">
        <span class="material-icons-round text-[16px]">edit</span> แก้ไข
    </a>
</div>
<div class="flex flex-col items-center flex-grow justify-start">
    
    <form id="imageUploadForm" action="" method="POST" enctype="multipart/form-data" class="relative mb-4 group">
        <div class="w-28 h-28 rounded-full p-1 bg-gradient-to-br from-pink-300 to-purple-300 shadow-md">
            <div class="bg-white dark:bg-card-dark rounded-full p-[3px] w-full h-full">
                <img alt="Profile Picture" class="w-full h-full rounded-full object-cover" src="<?= htmlspecialchars($profileImage) ?>"/>
            </div>
        </div>
        <input type="file" id="profileImageInput" name="profile_image" accept="image/*" class="hidden" onchange="document.getElementById('imageUploadForm').submit();">
        <button type="button" onclick="document.getElementById('profileImageInput').click();" class="absolute bottom-0 right-0 bg-primary text-white p-2 rounded-full shadow-md opacity-0 group-hover:opacity-100 transition transform translate-y-2 group-hover:translate-y-0 hover:scale-110 border-2 border-white dark:border-card-dark cursor-pointer">
            <span class="material-icons-round text-sm">camera_alt</span>
        </button>
    </form>

    <h3 class="text-xl font-bold text-gray-800 dark:text-white"><?= htmlspecialchars($displayName) ?></h3>
    <p class="text-gray-500 dark:text-gray-400 text-sm mb-6">ผู้ใช้งานระบบ Lumina</p>
    
    <div class="w-full space-y-3 mt-auto">
        <div class="flex items-center p-3 bg-background-light dark:bg-gray-800 rounded-xl border border-pink-50 dark:border-gray-700">
            <span class="material-icons-round text-primary mr-3 opacity-80">email</span>
            <span class="text-sm truncate dark:text-gray-300 font-medium"><?= htmlspecialchars($displayEmail) ?></span>
        </div>
        
        <div class="grid grid-cols-2 gap-y-3 gap-x-15">
            <div class="p-3 bg-background-light dark:bg-gray-800 rounded-xl border border-pink-50 dark:border-gray-700">
                <div class="text-[11px] text-gray-400 mb-1 flex items-center gap-1"><span class="material-icons-round text-[12px]">badge</span> ชื่อจริง</div>
                <div class="text-sm font-medium dark:text-gray-200 truncate" title="<?= htmlspecialchars($fullName) ?>">
                    <?= htmlspecialchars($fullName) ?>
                </div>
            </div>
            <div class="p-3 bg-background-light dark:bg-gray-800 rounded-xl border border-pink-50 dark:border-gray-700">
                <div class="text-[11px] text-gray-400 mb-1 flex items-center gap-1"><span class="material-icons-round text-[12px]">wc</span> เพศ</div>
                <div class="text-sm font-medium dark:text-gray-200"><?= $gender ?></div>
            </div>
            <div class="p-3 bg-background-light dark:bg-gray-800 rounded-xl border border-pink-50 dark:border-gray-700">
                <div class="text-[11px] text-gray-400 mb-1 flex items-center gap-1"><span class="material-icons-round text-[12px]">phone</span> เบอร์โทร</div>
                <div class="text-sm font-medium dark:text-gray-200"><?= htmlspecialchars($phone) ?></div>
            </div>
            <div class="p-3 bg-background-light dark:bg-gray-800 rounded-xl border border-pink-50 dark:border-gray-700">
                <div class="text-[11px] text-gray-400 mb-1 flex items-center gap-1"><span class="material-icons-round text-[12px]">cake</span> วันเกิด</div>
                <div class="text-sm font-medium dark:text-gray-200"><?= $birthdate ?></div>
            </div>
            <div class="p-3 bg-background-light dark:bg-gray-800 rounded-xl border border-pink-50 dark:border-gray-700">
                <div class="text-[11px] text-gray-400 mb-1 flex items-center gap-1"><span class="material-icons-round text-[12px]">calendar_today</span> วันที่สร้าง</div>
                <div class="text-xs font-medium dark:text-gray-200"><?= $created_at ?></div>
            </div>
            <div class="p-3 bg-background-light dark:bg-gray-800 rounded-xl border border-pink-50 dark:border-gray-700">
                <div class="text-[11px] text-gray-400 mb-1 flex items-center gap-1"><span class="material-icons-round text-[12px]">update</span> อัปเดตล่าสุด</div>
                <div class="text-xs font-medium dark:text-gray-200"><?= $updated_at ?></div>
            </div>
        </div>
    </div>
</div>
</div>
<div class="bg-card-light dark:bg-card-dark rounded-3xl p-6 shadow-soft border border-transparent dark:border-gray-700 flex flex-col justify-between h-full">
<div class="flex justify-between items-center mb-6">
<h2 class="text-xl font-bold flex items-center gap-2">
<span class="material-icons-round text-primary">local_shipping</span>
                                    คำสั่งซื้อล่าสุด
                                </h2>
<a class="text-xs text-gray-500 hover:text-primary transition" href="#">ดูทั้งหมด ></a>
</div>
<div class="flex-grow flex flex-col items-center justify-center text-center opacity-60">
<span class="material-icons-round text-5xl text-gray-300 dark:text-gray-600 mb-2">inbox</span>
<p class="text-gray-500 dark:text-gray-400 text-sm">ยังไม่มีคำสั่งซื้อล่าสุดในขณะนี้</p>
</div>
</div>
</div>
<div class="bg-blue-50 dark:bg-blue-900/20 rounded-2xl p-4 flex items-start gap-4 border border-blue-100 dark:border-blue-800/30">
<div class="bg-blue-100 dark:bg-blue-800 text-blue-600 dark:text-blue-300 p-2 rounded-xl">
<span class="material-icons-round">lightbulb</span>
</div>
<div>
<h4 class="font-bold text-blue-800 dark:text-blue-200 text-sm mb-1">เคล็ดลับความงามประจำวัน</h4>
<p class="text-xs text-blue-600 dark:text-blue-300">อย่าลืมทาครีมกันแดดทุกครั้งก่อนออกจากบ้าน แม้ในวันที่ไม่มีแดด เพื่อผิวที่อ่อนเยาว์และกระจ่างใสนะคะ!</p>
</div>
</div>
</section>
</div>
</main>

<script>
    // 1. ฟังก์ชันทำงานอัตโนมัติเมื่อโหลดหน้าเว็บ: เช็คว่าเคยเซฟธีมมืดไว้ไหม?
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }

    // 2. ฟังก์ชันเมื่อกดปุ่ม: สลับธีมและเซฟค่าลงระบบ
    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        
        // เช็คว่าตอนนี้เป็นธีมมืดหรือสว่าง แล้วเซฟทับลงไป
        if (htmlEl.classList.contains('dark')) {
            localStorage.setItem('theme', 'dark');
        } else {
            localStorage.setItem('theme', 'light');
        }
    }
</script>

<?php if (isset($_SESSION['swal_alert'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['swal_alert']['icon'] ?>',
        title: '<?= $_SESSION['swal_alert']['title'] ?>',
        text: '<?= $_SESSION['swal_alert']['text'] ?>',
        confirmButtonColor: '#F43F85',
        confirmButtonText: 'ตกลง',
        timer: 3000,
        timerProgressBar: true
    });
</script>
<?php 
    unset($_SESSION['swal_alert']); 
?>
<?php endif; ?>

</body></html>