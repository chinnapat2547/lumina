<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

/* ✅ FIX: ป้องกัน Login ค้าง (ห้ามลบโค้ดเดิม) */
$check_password_success = false;
$row = null;

$alertType = "";
$alertMsg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/connectdbuser.php';

    $login_id = trim($_POST['login_id']);
    $password = $_POST['password'];

    // 1. Admin
    $sqlAdmin = "SELECT * FROM `adminaccount` WHERE `admin_email` = ? OR `admin_username` = ?";
    if ($stmtAdmin = mysqli_prepare($conn, $sqlAdmin)) {
        mysqli_stmt_bind_param($stmtAdmin, "ss", $login_id, $login_id);
        mysqli_stmt_execute($stmtAdmin);
        $resultAdmin = mysqli_stmt_get_result($stmtAdmin);

        if (mysqli_num_rows($resultAdmin) === 1) {
            $rowAdmin = mysqli_fetch_assoc($resultAdmin);

            if (password_verify($password, $rowAdmin['admin_password']) || $password === $rowAdmin['admin_password']) {
                $_SESSION['admin_id'] = $rowAdmin['admin_id'];
                $_SESSION['admin_username'] = $rowAdmin['admin_username'];

                header("Location: ../home.php");
                exit;

                $alertType = "success";
                $alertMsg = "เข้าสู่ระบบผู้ดูแลระบบสำเร็จ!";
            } else {
                $alertType = "error";
                $alertMsg = "รหัสผ่านแอดมินไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
            }
        } else {
            // 2. User
            $sqlUser = "SELECT * FROM `account` WHERE `u_email` = ? OR `u_username` = ?";
            if ($stmtUser = mysqli_prepare($conn, $sqlUser)) {
                mysqli_stmt_bind_param($stmtUser, "ss", $login_id, $login_id);
                mysqli_stmt_execute($stmtUser);
                $resultUser = mysqli_stmt_get_result($stmtUser);

                if (mysqli_num_rows($resultUser) === 1) {
                    $rowUser = mysqli_fetch_assoc($resultUser);

                    if (password_verify($password, $rowUser['u_password']) || $password === $rowUser['u_password']) {
                        $_SESSION['u_id'] = $rowUser['u_id'];
                        $_SESSION['u_username'] = $rowUser['u_username'];
                        $_SESSION['u_name'] = $rowUser['u_name'];

                        header("Location: ../home.php");
                        exit;

                        $alertType = "success";
                        $alertMsg = "เข้าสู่ระบบสำเร็จ!";
                    } else {
                        $alertType = "error";
                        $alertMsg = "รหัสผ่านไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
                    }
                } else {
                    $alertType = "error";
                    $alertMsg = "ไม่พบอีเมลหรือชื่อผู้ใช้นี้ในระบบ กรุณาสมัครสมาชิก";
                }
                mysqli_stmt_close($stmtUser);
            }
        }
        mysqli_stmt_close($stmtAdmin);
    }
    mysqli_close($conn);
}

/* ❗ โค้ดนี้ยังอยู่ครบ แต่จะไม่ทำงาน → ระบบไม่ค้าง */
if ($check_password_success) {

    if ($row['role'] == 'admin') { 
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['role'] = 'admin';
        header("Location: home.php"); 
    } else { 
        $_SESSION['u_id'] = $row['id'];
        $_SESSION['role'] = 'user';
        header("Location: home.php"); 
    }
    exit;
}
?>
<!DOCTYPE html>
<html class="light" lang="th">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>เข้าสู่ระบบ LuminaBeauty</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script type="module">
  import { initializeApp } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-app.js";
  import { getAuth, signInWithPopup, GoogleAuthProvider, FacebookAuthProvider } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-auth.js";
</script>

<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#ee2b8c",
                        "background-light": "#fcf8fa",
                        "background-dark": "#221019",
                        "surface-light": "#ffffff",
                        "surface-dark": "#2d1622",
                    },
                    fontFamily: {
                        "display": ["Plus Jakarta Sans", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "1rem", "lg": "2rem", "xl": "3rem", "full": "9999px"},
                    animation: {
                        'blink': 'blink 4s infinite',
                    },
                    keyframes: {
                        blink: {
                            '0%, 45%, 55%, 100%': { transform: 'scaleY(1)' },
                            '50%': { transform: 'scaleY(0.1)' },
                        }
                    }
                },
            },
        }
    </script>
<style>
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background-color: #e7cfdb; border-radius: 20px; }
        .cloud-body {
            box-shadow: 
                inset -10px -10px 30px rgba(0,0,0,0.1),
                inset 10px 10px 30px rgba(255,255,255,0.8),
                20px 20px 60px rgba(0,0,0,0.1);
        }
        .parallax-item { transition: transform 0.1s ease-out; will-change: transform; }

        .gradient-border-box {
            position: relative;
            border-radius: 24px;
            background: transparent;
            z-index: 0;
        }
        
        .gradient-border-box::before {
            content: "";
            position: absolute;
            inset: -3px;
            border-radius: 38px; 
            padding: 4px; 
            background: linear-gradient(45deg, #ffecd2, #fcb69f, #e0c3fc, #ffecd2, #fcb69f); 
            background-size: 400% 400%;
            -webkit-mask: 
                linear-gradient(#fff 0 0) content-box, 
                linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            animation: moveBorderGradient 6s linear infinite;
            z-index: -1;
        }

        @keyframes moveBorderGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .corner-decor {
            position: absolute;
            font-size: 24px;
            opacity: 0.6;
            animation: sparkle 3s ease-in-out infinite;
        }
        @keyframes sparkle {
            0%, 100% { transform: scale(1); opacity: 0.6; }
            50% { transform: scale(1.2); opacity: 1; }
        }
        
        .eye-pupil { transition: transform 0.05s ease-out; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-[#1b0d14] dark:text-[#f3e7ed] antialiased min-h-screen flex flex-col">
<div class="flex min-h-screen w-full flex-row overflow-hidden">
    
<div id="visual-container" class="hidden lg:flex w-1/2 relative bg-gradient-to-br from-[#ffecd2] via-[#fcb69f] to-[#e0c3fc] items-center justify-center overflow-hidden">
    
    <div class="parallax-item absolute top-20 left-20 text-white/40" data-speed="0.02">
        <span class="material-symbols-outlined text-6xl">auto_awesome</span>
    </div>
    <div class="parallax-item absolute bottom-32 right-20 text-white/30" data-speed="0.03">
        <span class="material-symbols-outlined text-5xl">favorite</span>
    </div>

    <div class="relative w-[500px] h-[500px] flex items-center justify-center">
        <div class="absolute inset-0 bg-white/20 blur-3xl rounded-full scale-110"></div>
        
        <div class="parallax-item absolute z-20" data-speed="0.05">
            <div class="w-64 h-48 bg-gradient-to-b from-white to-[#ffeef6] rounded-[60px] cloud-body relative flex items-center justify-center">
                <div class="absolute left-8 top-1/2 w-8 h-4 bg-[#ffb7d5] rounded-full blur-md opacity-60"></div>
                <div class="absolute right-8 top-1/2 w-8 h-4 bg-[#ffb7d5] rounded-full blur-md opacity-60"></div>
                <div class="flex flex-col items-center gap-4 mt-4">
                    <div class="flex gap-10">
                        <div class="w-4 h-6 bg-[#2d1622] rounded-full relative overflow-hidden animate-blink">
                            <div class="absolute top-1 right-1 w-1.5 h-1.5 bg-white rounded-full eye-pupil"></div>
                        </div>
                        <div class="w-4 h-6 bg-[#2d1622] rounded-full relative overflow-hidden animate-blink">
                            <div class="absolute top-1 right-1 w-1.5 h-1.5 bg-white rounded-full eye-pupil"></div>
                        </div>
                    </div>
                    <div class="w-8 h-4 border-b-4 border-[#2d1622] rounded-full"></div>
                </div>
                <div class="absolute -top-8 left-10 w-24 h-24 bg-gradient-to-b from-white to-[#fff0f5] rounded-full -z-10"></div>
                <div class="absolute -top-4 right-8 w-20 h-20 bg-gradient-to-b from-white to-[#fff0f5] rounded-full -z-10"></div>
                <div class="absolute top-8 -left-8 w-20 h-20 bg-gradient-to-b from-white to-[#ffeef6] rounded-full -z-10"></div>
                <div class="absolute top-4 -right-6 w-16 h-16 bg-gradient-to-b from-white to-[#ffeef6] rounded-full -z-10"></div>
            </div>
        </div>

        <div class="parallax-item absolute -left-4 top-10 z-10" data-speed="0.08">
            <div class="w-40 h-32 bg-gradient-to-b from-[#f3e7ff] to-[#e0c3fc] rounded-[40px] cloud-body relative flex items-center justify-center opacity-90 transform -rotate-12">
                <div class="flex flex-col items-center gap-2 mt-2">
                    <div class="flex gap-6">
                        <div class="w-3 h-2 border-t-2 border-[#4a2c3d] rounded-full"></div>
                        <div class="w-3 h-2 border-t-2 border-[#4a2c3d] rounded-full"></div>
                    </div>
                    <div class="w-2 h-2 bg-[#4a2c3d] rounded-full"></div>
                </div>
                <div class="absolute -top-6 left-4 w-16 h-16 bg-[#f3e7ff] rounded-full -z-10"></div>
                <div class="absolute top-4 -left-6 w-14 h-14 bg-[#f3e7ff] rounded-full -z-10"></div>
            </div>
        </div>

        <div class="parallax-item absolute right-0 bottom-10 z-30" data-speed="0.06">
            <div class="w-32 h-24 bg-gradient-to-b from-[#fff5eb] to-[#ffdec2] rounded-[30px] cloud-body relative flex items-center justify-center transform rotate-6">
                <div class="flex flex-col items-center gap-1 mt-1">
                    <div class="flex gap-4">
                        <div class="w-2 h-2 bg-[#5c3a2e] rounded-full"></div>
                        <div class="w-2 h-2 bg-[#5c3a2e] rounded-full"></div>
                    </div>
                    <div class="absolute top-1/2 left-2 w-3 h-2 bg-[#ff9e9e] blur-sm rounded-full"></div>
                    <div class="absolute top-1/2 right-2 w-3 h-2 bg-[#ff9e9e] blur-sm rounded-full"></div>
                    <div class="w-3 h-1.5 bg-[#5c3a2e] rounded-b-full"></div>
                </div>
                <div class="absolute -top-4 right-2 w-12 h-12 bg-[#fff5eb] rounded-full -z-10"></div>
                <div class="absolute top-2 -right-4 w-10 h-10 bg-[#fff5eb] rounded-full -z-10"></div>
            </div>
        </div>
    </div>

    <div class="parallax-item absolute bottom-12 left-12 right-12 text-white z-40" data-speed="0.01">
        <div class="backdrop-blur-sm bg-white/10 p-6 rounded-3xl border border-white/20">
            <div class="flex items-center gap-3 mb-2">
                <div class="size-8 rounded-full bg-white flex items-center justify-center text-primary shadow-lg">
                    <span class="material-symbols-outlined text-xl">spa</span>
                </div>
                <span class="text-sm font-bold tracking-wider uppercase text-white drop-shadow-md">Lumina Beauty</span>
            </div>
            <h2 class="text-3xl font-bold leading-tight mb-2 drop-shadow-md">สวัสดีลูกค้าทุกท่าน!</h2>
            <p class="text-base text-white/90 drop-shadow-sm">เข้าร่วมกับเรา และค้นพบผลิตภัณฑ์ที่จะทำให้คุณรู้สึกราวกับล่องลอยอยู่บนปุยเมฆเหมือนดั่งนางฟ้า</p>
        </div>
    </div>
</div>

<div class="flex w-full lg:w-1/2 flex-col items-center justify-center p-6 md:p-12 lg:p-24 bg-surface-light dark:bg-surface-dark relative">
    <div class="lg:hidden absolute top-6 left-6 flex items-center gap-2 text-primary">
        <span class="material-symbols-outlined text-3xl">spa</span>
        <span class="text-xl font-bold tracking-tight text-[#1b0d14] dark:text-white">Lumina</span>
    </div>

    <div class="w-full max-w-lg gradient-border-box p-10 md:p-14 relative">
        
        <span class="material-symbols-outlined corner-decor text-[#fcb69f]" style="top: 10px; left: 10px;">auto_awesome</span>
        <span class="material-symbols-outlined corner-decor text-[#e0c3fc]" style="bottom: 10px; right: 10px;">auto_awesome</span>
        <span class="material-symbols-outlined corner-decor text-[#ffecd2]" style="top: 10px; right: 10px; transform: scale(0.7);">star</span>
        
        <div class="flex flex-col gap-8 relative z-10">
            <div class="flex flex-col gap-2">
                <div class="hidden lg:flex items-center gap-2 text-primary mb-2">
                    <span class="material-symbols-outlined text-4xl">spa</span>
                </div>
                <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-[#1b0d14] dark:text-white">ยินดีต้อนรับกลับมา</h1>
                <p class="text-[#9a4c73] dark:text-[#dcbccc] text-base">กรุณากรอกข้อมูลเพื่อเข้าสู่ระบบ</p>
            </div>

            <form id="loginForm" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="flex flex-col gap-5 w-full" method="POST" novalidate onsubmit="return validateLoginForm(event)">
                <div class="flex flex-col gap-2 group">
                    <label class="text-sm font-semibold text-[#1b0d14] dark:text-[#f3e7ed] ml-1" for="login_id">อีเมล หรือ ชื่อผู้ใช้</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-[#9a4c73]">
                            <span class="material-symbols-outlined text-[20px]">person</span>
                        </div>
                        <input class="w-full pl-11 pr-4 py-3.5 bg-[#fcf8fa] dark:bg-[#1f0e16] border border-[#e7cfdb] dark:border-[#5a3a4a] rounded-full text-[#1b0d14] dark:text-white placeholder:text-[#9a4c73]/50 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200" id="login_id" name="login_id" placeholder="กรอกอีเมล หรือ ชื่อผู้ใช้" type="text"/>
                    </div>
                </div>
                <div class="flex flex-col gap-2 group">
                    <label class="text-sm font-semibold text-[#1b0d14] dark:text-[#f3e7ed] ml-1" for="password">รหัสผ่าน</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-[#9a4c73]">
                            <span class="material-symbols-outlined text-[20px]">lock</span>
                        </div>
                        <input class="w-full pl-11 pr-4 py-3.5 bg-[#fcf8fa] dark:bg-[#1f0e16] border border-[#e7cfdb] dark:border-[#5a3a4a] rounded-full text-[#1b0d14] dark:text-white placeholder:text-[#9a4c73]/50 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200" id="password" name="password" placeholder="กรอกรหัสผ่านของคุณ" type="password"/>
                        <button class="absolute inset-y-0 right-0 pr-4 flex items-center text-[#9a4c73] hover:text-primary transition-colors cursor-pointer" type="button" onclick="togglePassword()">
                            <span id="password-icon" class="material-symbols-outlined text-[20px]">visibility_off</span>
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-between px-1">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <div class="relative flex items-center">
                            <input class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-[#e7cfdb] dark:border-[#5a3a4a] bg-transparent checked:bg-primary checked:border-primary transition-all" type="checkbox"/>
                            <span class="material-symbols-outlined absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-[16px] text-white opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity">check</span>
                        </div>
                        <span class="text-sm font-medium text-[#5a3a4a] dark:text-[#dcbccc] group-hover:text-primary transition-colors">จดจำฉัน</span>
                    </label>
                    <a class="text-sm font-bold text-primary hover:text-[#c41e6e] transition-colors" href="#">ลืมรหัสผ่าน?</a>
                </div>
                <button type="submit" class="w-full mt-2 bg-primary hover:bg-[#d6207a] text-white text-base font-bold py-3.5 px-6 rounded-full shadow-lg shadow-primary/30 transform active:scale-[0.98] transition-all duration-200 flex items-center justify-center gap-2">
                    <span>เข้าสู่ระบบ</span>
                    <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                </button>
            </form>

            <div class="relative flex items-center py-2">
                <div class="flex-grow border-t border-[#e7cfdb] dark:border-[#5a3a4a]"></div>
                <span class="flex-shrink-0 mx-4 text-sm font-medium text-[#9a4c73]">หรือเข้าสู่ระบบด้วย</span>
                <div class="flex-grow border-t border-[#e7cfdb] dark:border-[#5a3a4a]"></div>
            </div>

            <div class="flex justify-center gap-4">
                <button onclick="mockGoogleLogin()" class="flex items-center justify-center size-12 rounded-full border border-[#e7cfdb] dark:border-[#5a3a4a] bg-white dark:bg-[#1f0e16] hover:bg-[#fcf8fa] dark:hover:bg-[#2d1622] hover:border-primary/50 transition-all duration-200 group">
                    <svg class="size-5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"></path>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"></path>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"></path>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"></path>
                    </svg>
                </button>
                <button onclick="mockSocialLogin('Apple')" class="flex items-center justify-center size-12 rounded-full border border-[#e7cfdb] dark:border-[#5a3a4a] bg-white dark:bg-[#1f0e16] hover:bg-[#fcf8fa] dark:hover:bg-[#2d1622] hover:border-primary/50 transition-all duration-200 text-[#1b0d14] dark:text-white group">
                    <svg class="size-5 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.05 20.28c-.98.95-2.05.88-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.64 3.4 1.63-3.12 1.88-2.68 5.86.1 6.94-.5 1.49-1.15 2.96-2.15 4.44zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"></path>
                    </svg>
                </button>
                <button onclick="mockSocialLogin('Facebook')" class="flex items-center justify-center size-12 rounded-full border border-[#e7cfdb] dark:border-[#5a3a4a] bg-white dark:bg-[#1f0e16] hover:bg-[#fcf8fa] dark:hover:bg-[#2d1622] hover:border-primary/50 transition-all duration-200 text-[#1877F2] group">
                    <svg class="size-6 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2.04C6.5 2.04 2 6.53 2 12.06C2 17.06 5.66 21.21 10.44 21.96V14.96H7.9V12.06H10.44V9.85C10.44 7.34 11.93 5.96 14.22 5.96C15.31 5.96 16.45 6.15 16.45 6.15V8.62H15.19C13.95 8.62 13.56 9.39 13.56 10.18V12.06H16.34L15.89 14.96H13.56V21.96A10 10 0 0 0 22 12.06C22 6.53 17.5 2.04 12 2.04Z"></path>
                    </svg>
                </button>
            </div>

            <div class="text-center mt-4">
                <p class="text-sm text-[#5a3a4a] dark:text-[#dcbccc]">
                    ยังไม่เป็นสมาชิก Lumina? 
                    <a class="font-bold text-primary hover:text-[#c41e6e] transition-colors" href="register.php">สมัครสมาชิก</a>
                </p>
            </div>
        </div>
    </div>
    
    <div class="absolute bottom-0 right-0 p-8 hidden lg:block pointer-events-none opacity-20">
        <span class="material-symbols-outlined text-[120px] text-primary rotate-12">brush</span>
    </div>
</div>
</div>

<script>
    function validateLoginForm(event) {
        const login_id = document.getElementById('login_id').value.trim();
        const password = document.getElementById('password').value;

        // ถ้าข้อมูลไม่ครบ ให้หยุดการทำงานแล้วแจ้งเตือน
        if (!login_id || !password) {
            event.preventDefault(); 
            Swal.fire({
                icon: 'warning',
                title: 'ข้อมูลไม่ครบถ้วน',
                text: 'กรุณากรอกอีเมล หรือ ชื่อผู้ใช้ และรหัสผ่านให้ครบถ้วนค่ะ',
                confirmButtonColor: '#ee2b8c'
            });
            return false;
        }
        
        // ถ้าข้อมูลครบ ปล่อยให้ฟอร์มทำงานแบบธรรมชาติ (return true) เพื่อส่งข้อมูลไปให้ PHP ด้านบนประมวลผล
        return true;
    }

    <?php if($alertType === 'success'): ?>
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ!',
            text: '<?php echo $alertMsg; ?>',
            timer: 1500,
            showConfirmButton: false
        }).then(() => {
            window.location.href = '../home.php'; 
        });
    <?php elseif($alertType === 'error'): ?>
        Swal.fire({
            icon: 'error',
            title: 'ล้มเหลว',
            text: '<?php echo $alertMsg; ?>',
            confirmButtonColor: '#ee2b8c'
        });
    <?php endif; ?>

    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const icon = document.getElementById('password-icon');
        
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            icon.textContent = "visibility";
        } else {
            passwordInput.type = "password";
            icon.textContent = "visibility_off";
        }
    }

    document.addEventListener("mousemove", (e) => {
        const visualContainer = document.getElementById('visual-container');
        const items = document.querySelectorAll('.parallax-item');
        
        const centerX = window.innerWidth / 4; 
        const centerY = window.innerHeight / 2;

        const mouseX = e.clientX;
        const mouseY = e.clientY;

        items.forEach(item => {
            const speed = parseFloat(item.getAttribute('data-speed')) || 0.05;
            const x = (mouseX - centerX) * speed;
            const y = (mouseY - centerY) * speed;
            item.style.transform = `translate(${x}px, ${y}px)`;
        });

        const pupils = document.querySelectorAll('.eye-pupil');
        pupils.forEach(pupil => {
            const eye = pupil.parentElement;
            const rect = eye.getBoundingClientRect();
            const eyeCenterX = rect.left + rect.width / 2;
            const eyeCenterY = rect.top + rect.height / 2;
            
            const angle = Math.atan2(mouseY - eyeCenterY, mouseX - eyeCenterX);
            const distance = Math.min(3, Math.hypot(mouseX - eyeCenterX, mouseY - eyeCenterY));
            
            const moveX = Math.cos(angle) * distance;
            const moveY = Math.sin(angle) * distance;
            
            pupil.style.transform = `translate(${moveX}px, ${moveY}px)`;
        });
    });

    function mockGoogleLogin() {
        const width = 500;
        const height = 600;
        const left = (window.innerWidth - width) / 2;
        const top = (window.innerHeight - height) / 2;
        const popup = window.open("", "Google Login", `width=${width},height=${height},top=${top},left=${left}`);
        
        if (popup) {
            popup.document.write(`
                <h2 style="font-family:sans-serif; text-align:center; margin-top:50px;">Connecting to Google...</h2>
                <p style="font-family:sans-serif; text-align:center;">(Simulation Mode)</p>
            `);
            setTimeout(() => {
                popup.close();
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: 'Login with Google สำเร็จ!',
                    confirmButtonColor: '#ee2b8c'
                }).then(() => {
                    window.location.href = "../home.php";
                });
            }, 1500);
        }
    }

    function mockSocialLogin(provider) {
        Swal.fire({
            icon: 'info',
            title: `เชื่อมต่อ ${provider}`,
            text: `กำลังเชื่อมต่อกับ ${provider}... (ในโหมดจริงจะเด้งหน้าต่าง Login)`,
            showConfirmButton: false,
            timer: 1500
        }).then(() => {
             window.location.href = "../home.php";
        });
    }
</script>

</body>
</html>