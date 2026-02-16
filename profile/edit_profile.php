<?php
session_start();
require_once '../config/connectdbuser.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['u_id'])) {
    header("Location: login.php");
    exit();
}

$u_id = $_SESSION['u_id'];

// ==========================================
// 1. จัดการเมื่อมีการกดปุ่ม "บันทึกข้อมูล" (POST Request)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['u_name'] ?? '';
    $phone = $_POST['u_phone'] ?? '';
    $gender = $_POST['u_gender'] ?? '';
    $birthdate = !empty($_POST['u_birthdate']) ? $_POST['u_birthdate'] : NULL;

    // อัปเดตตาราง account (ชื่อจริง u_name)
    $sqlAccount = "UPDATE `account` SET u_name = ? WHERE u_id = ?";
    if ($stmtAcc = mysqli_prepare($conn, $sqlAccount)) {
        mysqli_stmt_bind_param($stmtAcc, "si", $name, $u_id);
        mysqli_stmt_execute($stmtAcc);
        mysqli_stmt_close($stmtAcc);
    }

    // อัปเดตตาราง user (เบอร์โทร, เพศ, วันเกิด) 
    // ใช้ INSERT ... ON DUPLICATE KEY UPDATE เผื่อกรณีคนที่เพิ่งสมัครและยังไม่มี Row ในตาราง user
    $sqlUser = "INSERT INTO `user` (u_id, u_phone, u_gender, u_birthdate) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE u_phone = ?, u_gender = ?, u_birthdate = ?";
    if ($stmtUser = mysqli_prepare($conn, $sqlUser)) {
        mysqli_stmt_bind_param($stmtUser, "issssss", $u_id, $phone, $gender, $birthdate, $phone, $gender, $birthdate);
        mysqli_stmt_execute($stmtUser);
        mysqli_stmt_close($stmtUser);
    }

    // บันทึกเสร็จให้เด้งกลับไปหน้าบัญชี
    header("Location: account.php");
    exit();
}

// ==========================================
// 2. ดึงข้อมูลเดิมมาแสดงในฟอร์ม
// ==========================================
$userData = [];
$sql = "SELECT a.u_username, a.u_email, a.u_name, 
               u.u_phone, u.u_gender, u.u_birthdate 
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
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Lumina Beauty - แก้ไขข้อมูลส่วนตัว</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
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
                    },
                    animation: {
                        'blink': 'blink 4s infinite',
                        'float': 'float 6s ease-in-out infinite',
                        'float-delayed': 'float 6s ease-in-out 3s infinite',
                        'float-slow': 'float 8s ease-in-out 1s infinite',
                    },
                    keyframes: {
                        blink: {
                            '0%, 45%, 55%, 100%': { transform: 'scaleY(1)' },
                            '50%': { transform: 'scaleY(0.1)' },
                        },
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
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .dark .glass-panel {
            background: rgba(45, 38, 53, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        /* Custom Input Styles */
        .form-input {
            width: 100%;
            border-radius: 1rem;
            border: 1px solid #FBCFE8;
            background-color: #FFF5F7;
            padding: 0.75rem 1.25rem;
            font-size: 0.95rem;
            color: #374151;
            transition: all 0.3s ease;
            outline: none;
        }
        .form-input:focus {
            border-color: #F43F85;
            box-shadow: 0 0 0 3px rgba(244, 63, 133, 0.2);
            background-color: #FFFFFF;
        }
        .dark .form-input {
            border-color: #4B5563;
            background-color: #1F2937;
            color: #E5E7EB;
        }
        .dark .form-input:focus {
            border-color: #F43F85;
            background-color: #374151;
        }
        .form-input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* ☁️ Styles สำหรับก้อนเมฆ */
        .cloud-body {
            box-shadow: 
                inset -10px -10px 30px rgba(0,0,0,0.05),
                inset 10px 10px 30px rgba(255,255,255,0.9),
                15px 15px 40px rgba(0,0,0,0.08);
        }
        .parallax-item { transition: transform 0.1s ease-out; will-change: transform; }
        .eye-pupil { transition: transform 0.05s ease-out; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark transition-colors duration-300 min-h-screen relative overflow-x-hidden flex flex-col">

<div id="visual-container" class="fixed top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0 flex items-center justify-center">
    <div class="absolute top-[10%] left-[10%] w-[40%] h-[40%] rounded-full bg-pink-200 dark:bg-pink-900 blur-[100px] opacity-40 animate-pulse"></div>
    <div class="absolute bottom-[10%] right-[10%] w-[50%] h-[50%] rounded-full bg-purple-200 dark:bg-purple-900 blur-[100px] opacity-30"></div>

    <div class="parallax-item hidden lg:block absolute top-[25%] left-[8%] z-10 animate-float" data-speed="0.04">
        <div class="w-48 h-32 bg-gradient-to-b from-white to-[#ffeef6] rounded-[40px] cloud-body relative flex items-center justify-center transform hover:scale-105 transition-transform duration-300 scale-90 opacity-80 dark:opacity-40">
            <div class="absolute left-4 top-1/2 w-5 h-2.5 bg-[#ffb7d5] rounded-full blur-sm opacity-60"></div>
            <div class="absolute right-4 top-1/2 w-5 h-2.5 bg-[#ffb7d5] rounded-full blur-sm opacity-60"></div>
            <div class="flex flex-col items-center gap-2 mt-2">
                <div class="flex gap-6">
                    <div class="w-2.5 h-4 bg-[#2d1622] rounded-full relative overflow-hidden animate-blink">
                        <div class="absolute top-1 right-0.5 w-1 h-1 bg-white rounded-full eye-pupil"></div>
                    </div>
                    <div class="w-2.5 h-4 bg-[#2d1622] rounded-full relative overflow-hidden animate-blink">
                        <div class="absolute top-1 right-0.5 w-1 h-1 bg-white rounded-full eye-pupil"></div>
                    </div>
                </div>
                <div class="w-4 h-2 border-b-2 border-[#2d1622] rounded-full"></div>
            </div>
            <div class="absolute -top-5 left-6 w-16 h-16 bg-gradient-to-b from-white to-[#f0f8ff] rounded-full -z-10"></div>
            <div class="absolute -top-2 right-4 w-14 h-14 bg-gradient-to-b from-white to-[#f0f8ff] rounded-full -z-10"></div>
            <div class="absolute top-4 -left-4 w-12 h-12 bg-gradient-to-b from-white to-[#e6f2ff] rounded-full -z-10"></div>
        </div>
    </div>

    <div class="parallax-item hidden md:block absolute bottom-[20%] right-[10%] z-20 animate-float-delayed" data-speed="0.06">
        <div class="w-32 h-24 bg-gradient-to-b from-[#fff5eb] to-[#ffdec2] rounded-[24px] cloud-body relative flex items-center justify-center transform -rotate-12 opacity-80 dark:opacity-40">
            <div class="flex flex-col items-center gap-1.5 mt-1">
                <div class="flex gap-4">
                    <div class="w-2 h-2 bg-[#4a2c3d] rounded-full"></div>
                    <div class="w-2 h-2 bg-[#4a2c3d] rounded-full"></div>
                </div>
                <div class="w-1.5 h-1.5 bg-[#ff9e9e] rounded-full blur-[1px]"></div>
            </div>
            <div class="absolute -top-3 right-3 w-10 h-10 bg-[#fff5eb] rounded-full -z-10"></div>
            <div class="absolute top-3 -left-3 w-8 h-8 bg-[#fff5eb] rounded-full -z-10"></div>
        </div>
    </div>

    <div class="parallax-item hidden lg:block absolute top-[15%] right-[25%] z-10 animate-float-slow" data-speed="0.03">
        <div class="w-24 h-16 bg-gradient-to-b from-[#f3e7ff] to-[#e0c3fc] rounded-[20px] cloud-body relative flex items-center justify-center transform rotate-6 opacity-70 dark:opacity-30">
            <div class="flex flex-col items-center gap-1 mt-1">
                <div class="flex gap-2">
                    <div class="w-1.5 h-1.5 bg-[#1a4731] rounded-full"></div>
                    <div class="w-1.5 h-1.5 bg-[#1a4731] rounded-full"></div>
                </div>
                <div class="w-1.5 h-1 bg-[#1a4731] rounded-b-full"></div>
            </div>
            <div class="absolute -top-2 left-2 w-8 h-8 bg-[#f3e7ff] rounded-full -z-10"></div>
        </div>
    </div>
</div>
<nav class="sticky top-0 z-50 glass-panel shadow-sm px-6 py-4 relative z-50">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
        <a href="../home.php" class="flex items-center space-x-2 cursor-pointer hover:opacity-80 transition-opacity">
            <span class="material-icons-round text-primary text-4xl">spa</span>
            <span class="font-bold text-2xl tracking-tight text-primary">Lumina</span>
        </a>
        <button class="hover:text-primary transition flex items-center justify-center" onclick="toggleTheme()">
                <span class="material-icons-round dark:hidden text-2xl text-gray-500">dark_mode</span>
                <span class="material-icons-round hidden dark:block text-yellow-400 text-2xl">light_mode</span>
        </button>
    </div>
</nav>

<main class="relative z-10 flex-grow flex items-center justify-center py-12 px-4 sm:px-6">
    <div class="w-full max-w-2xl bg-card-light dark:bg-card-dark rounded-3xl shadow-soft overflow-hidden border border-white dark:border-gray-800 backdrop-blur-sm">
        
        <div class="bg-gradient-to-r from-pink-400 to-purple-400 p-8 text-center relative overflow-hidden">
            <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white opacity-20 rounded-full blur-xl animate-pulse"></div>
            <div class="absolute bottom-0 left-0 -mb-4 -ml-4 w-24 h-24 bg-white opacity-20 rounded-full blur-xl animate-pulse"></div>
            <h1 class="relative z-10 text-3xl font-bold text-white flex items-center justify-center gap-2">
                <span class="material-icons-round text-3xl">manage_accounts</span>
                แก้ไขข้อมูลส่วนตัว
            </h1>
            <p class="relative z-10 text-pink-100 mt-2 text-sm">อัปเดตข้อมูลของคุณเพื่อให้เราดูแลคุณได้ดียิ่งขึ้น</p>
        </div>

        <div class="p-8 sm:p-10">
            <form action="edit_profile.php" method="POST" class="space-y-6">
                
                <div>
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 border-b border-pink-100 dark:border-gray-700 pb-2 flex items-center gap-2">
                        <span class="material-icons-round text-primary text-[20px]">lock</span> ข้อมูลบัญชี
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1 ml-1">ชื่อผู้ใช้งาน (Username)</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($userData['u_username'] ?? '') ?>" disabled>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1 ml-1">อีเมล (Email)</label>
                            <input type="email" class="form-input" value="<?= htmlspecialchars($userData['u_email'] ?? '') ?>" disabled>
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-2 ml-1">* หากต้องการเปลี่ยนชื่อผู้ใช้และอีเมล สามารถเปลี่ยนได้ที่รายละเอียดบัญชี</p>
                </div>

                <div class="pt-4">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 border-b border-pink-100 dark:border-gray-700 pb-2 flex items-center gap-2">
                        <span class="material-icons-round text-primary text-[20px]">face</span> ข้อมูลส่วนตัว
                    </h3>
                    
                    <div class="space-y-5">
                        <div>
                            <label for="u_name" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1 ml-1">ชื่อ-นามสกุล (ชื่อจริง)</label>
                            <input type="text" id="u_name" name="u_name" class="form-input" placeholder="ระบุชื่อ-นามสกุลของคุณ" value="<?= htmlspecialchars($userData['u_name'] ?? '') ?>" required>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label for="u_phone" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1 ml-1">เบอร์โทรศัพท์</label>
                                <input type="tel" id="u_phone" name="u_phone" class="form-input" placeholder="08X-XXX-XXXX" value="<?= htmlspecialchars($userData['u_phone'] ?? '') ?>">
                            </div>
                            <div>
                                <label for="u_gender" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1 ml-1">เพศ</label>
                                <select id="u_gender" name="u_gender" class="form-input cursor-pointer">
                                    <option value="" disabled <?= empty($userData['u_gender']) ? 'selected' : '' ?>>เลือกเพศ</option>
                                    <option value="Female" <?= ($userData['u_gender'] ?? '') == 'Female' ? 'selected' : '' ?>>หญิง (Female)</option>
                                    <option value="Male" <?= ($userData['u_gender'] ?? '') == 'Male' ? 'selected' : '' ?>>ชาย (Male)</option>
                                    <option value="Other" <?= ($userData['u_gender'] ?? '') == 'Other' ? 'selected' : '' ?>>อื่นๆ (Other)</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="u_birthdate" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1 ml-1">วัน/เดือน/ปีเกิด</label>
                            <input type="date" id="u_birthdate" name="u_birthdate" class="form-input cursor-pointer" value="<?= htmlspecialchars($userData['u_birthdate'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-pink-50 dark:border-gray-700 mt-8">
                    <a href="account.php" class="w-full sm:w-1/3 flex items-center justify-center gap-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-semibold py-3.5 rounded-xl transition duration-300">
                        ยกเลิก
                    </a>
                    <button type="submit" class="w-full sm:w-2/3 flex items-center justify-center gap-2 bg-gradient-to-r from-primary to-pink-500 hover:from-pink-500 hover:to-purple-500 text-white font-bold py-3.5 rounded-xl shadow-[0_4px_15px_rgba(236,45,136,0.4)] hover:shadow-[0_6px_20px_rgba(236,45,136,0.6)] transform hover:-translate-y-0.5 transition duration-300">
                        <span class="material-icons-round">save</span>
                        บันทึกข้อมูล
                    </button>
                </div>

            </form>
        </div>
    </div>
</main>

<script>
    document.addEventListener("mousemove", (e) => {
        // จำกัดให้แสดงผลแค่หน้าจอคอมพิวเตอร์ (ไม่รบกวนมือถือ)
        if (window.innerWidth < 1024) return;

        const items = document.querySelectorAll('.parallax-item');
        const centerX = window.innerWidth / 2;
        const centerY = window.innerHeight / 2;
        const mouseX = e.clientX;
        const mouseY = e.clientY;

        // ขยับก้อนเมฆทั้งหมด
        items.forEach(item => {
            const speed = parseFloat(item.getAttribute('data-speed')) || 0.05;
            const x = (mouseX - centerX) * speed;
            const y = (mouseY - centerY) * speed;
            item.style.transform = `translate(${x}px, ${y}px)`;
        });

        // ขยับลูกตาดำก้อนเมฆสีขาว
        const pupils = document.querySelectorAll('.eye-pupil');
        pupils.forEach(pupil => {
            const eye = pupil.parentElement;
            const rect = eye.getBoundingClientRect();
            const eyeCenterX = rect.left + rect.width / 2;
            const eyeCenterY = rect.top + rect.height / 2;
            
            const angle = Math.atan2(mouseY - eyeCenterY, mouseX - eyeCenterX);
            // จำกัดระยะลูกตาดำไม่ให้หลุดขอบตา
            const distance = Math.min(2, Math.hypot(mouseX - eyeCenterX, mouseY - eyeCenterY) * 0.1); 
            
            const moveX = Math.cos(angle) * distance;
            const moveY = Math.sin(angle) * distance;
            
            pupil.style.transform = `translate(${moveX}px, ${moveY}px)`;
        });
    });

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

</body></html>