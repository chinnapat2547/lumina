<?php
session_start();
// --- ส่วนตั้งค่า Debug (แสดง Error ทั้งหมด) ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

$alertType = "";
$alertMsg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบ Path ไฟล์ connectdbuser.php ให้แน่ใจว่าถูกต้อง
    // ถ้าไฟล์ login.php อยู่ในโฟลเดอร์ย่อย ต้องใช้ ../ 
    // แต่ถ้าอยู่ที่ root ให้ลบ ../ ออก
    require_once '../config/connectdbuser.php'; 

    $login_id = trim($_POST['login_id']);
    $password = $_POST['password'];

    // -----------------------------------------------------------
    // 1. ค้นหาในตาราง adminaccount (ไม่มีขีดล่างตามรูป)
    // -----------------------------------------------------------
    $sqlAdmin = "SELECT * FROM `adminaccount` WHERE `admin_email` = ? OR `admin_username` = ?";
    
    // ตรวจสอบว่าคำสั่ง SQL ถูกต้องหรือไม่
    if ($stmtAdmin = mysqli_prepare($conn, $sqlAdmin)) {
        mysqli_stmt_bind_param($stmtAdmin, "ss", $login_id, $login_id);
        
        if (mysqli_stmt_execute($stmtAdmin)) {
            $resultAdmin = mysqli_stmt_get_result($stmtAdmin);

            if (mysqli_num_rows($resultAdmin) === 1) {
                $rowAdmin = mysqli_fetch_assoc($resultAdmin);
                
                // ตรวจสอบรหัสผ่าน (รองรับทั้ง Hash และ Plain Text)
                if (password_verify($password, $rowAdmin['admin_password']) || $password === $rowAdmin['admin_password']) {
                    
                    $_SESSION['admin_id'] = $rowAdmin['admin_id'];
                    $_SESSION['admin_username'] = $rowAdmin['admin_username'];
                    // เพิ่มตัวแปรเช็คสถานะแอดมิน
                    $_SESSION['is_admin'] = true; 

                    $alertType = "success";
                    $alertMsg = "เข้าสู่ระบบผู้ดูแลระบบสำเร็จ!";
                } else {
                    $alertType = "error";
                    $alertMsg = "รหัสผ่านแอดมินไม่ถูกต้อง";
                }
            } else {
                // -----------------------------------------------------------
                // 2. หากไม่พบใน adminaccount ให้มาค้นหาในตาราง account (User)
                // -----------------------------------------------------------
                $sqlUser = "SELECT * FROM `account` WHERE `u_email` = ? OR `u_username` = ?";
                
                if ($stmtUser = mysqli_prepare($conn, $sqlUser)) {
                    mysqli_stmt_bind_param($stmtUser, "ss", $login_id, $login_id);
                    
                    if (mysqli_stmt_execute($stmtUser)) {
                        $resultUser = mysqli_stmt_get_result($stmtUser);

                        if (mysqli_num_rows($resultUser) === 1) {
                            $rowUser = mysqli_fetch_assoc($resultUser);
                            
                            if (password_verify($password, $rowUser['u_password']) || $password === $rowUser['u_password']) {
                                
                                $_SESSION['u_id'] = $rowUser['u_id'];
                                $_SESSION['u_username'] = $rowUser['u_username'];
                                $_SESSION['u_name'] = $rowUser['u_name'];
                                
                                $alertType = "success";
                                $alertMsg = "เข้าสู่ระบบสำเร็จ!";
                            } else {
                                $alertType = "error";
                                $alertMsg = "รหัสผ่านไม่ถูกต้อง";
                            }
                        } else {
                            $alertType = "error";
                            $alertMsg = "ไม่พบข้อมูลบัญชีนี้ในระบบ";
                        }
                    } else {
                        // Error ตอน Execute User
                        $alertType = "error";
                        $alertMsg = "Exec Error (User): " . mysqli_stmt_error($stmtUser);
                    }
                    mysqli_stmt_close($stmtUser);
                } else {
                    // Error ตอน Prepare User SQL (เช่น ชื่อคอลัมน์ u_email ผิด)
                    $alertType = "error";
                    $alertMsg = "SQL Error (User): " . mysqli_error($conn);
                }
            }
        } else {
             // Error ตอน Execute Admin
             $alertType = "error";
             $alertMsg = "Exec Error (Admin): " . mysqli_stmt_error($stmtAdmin);
        }
        mysqli_stmt_close($stmtAdmin);
    } else {
        // Error ตอน Prepare Admin SQL (เช่น ตาราง adminaccount ไม่มีคอลัมน์ admin_email)
        $alertType = "error";
        $alertMsg = "SQL Error (Admin Database): " . mysqli_error($conn);
    }
    mysqli_close($conn);
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
                animation: { 'blink': 'blink 4s infinite', },
                keyframes: { blink: { '0%, 45%, 55%, 100%': { transform: 'scaleY(1)' }, '50%': { transform: 'scaleY(0.1)' }, } }
            },
        },
    }
</script>
<style>
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background-color: #e7cfdb; border-radius: 20px; }
    .cloud-body {
        box-shadow: inset -10px -10px 30px rgba(0,0,0,0.1), inset 10px 10px 30px rgba(255,255,255,0.8), 20px 20px 60px rgba(0,0,0,0.1);
    }
    .parallax-item { transition: transform 0.1s ease-out; will-change: transform; }
    .gradient-border-box {
        position: relative; border-radius: 24px; background: transparent; z-index: 0;
    }
    .gradient-border-box::before {
        content: ""; position: absolute; inset: -3px; border-radius: 38px; padding: 4px; 
        background: linear-gradient(45deg, #ffecd2, #fcb69f, #e0c3fc, #ffecd2, #fcb69f); 
        background-size: 400% 400%;
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: xor; mask-composite: exclude;
        animation: moveBorderGradient 6s linear infinite; z-index: -1;
    }
    @keyframes moveBorderGradient { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
    .corner-decor { position: absolute; font-size: 24px; opacity: 0.6; animation: sparkle 3s ease-in-out infinite; }
    @keyframes sparkle { 0%, 100% { transform: scale(1); opacity: 0.6; } 50% { transform: scale(1.2); opacity: 1; } }
    .eye-pupil { transition: transform 0.05s ease-out; }
</style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-[#1b0d14] dark:text-[#f3e7ed] antialiased min-h-screen flex flex-col">

<div class="flex min-h-screen w-full flex-row overflow-hidden">
    
    <div id="visual-container" class="hidden lg:flex w-1/2 relative bg-gradient-to-br from-[#ffecd2] via-[#fcb69f] to-[#e0c3fc] items-center justify-center overflow-hidden">
        <div class="parallax-item absolute top-20 left-20 text-white/40" data-speed="0.02"><span class="material-symbols-outlined text-6xl">auto_awesome</span></div>
        <div class="parallax-item absolute bottom-32 right-20 text-white/30" data-speed="0.03"><span class="material-symbols-outlined text-5xl">favorite</span></div>

        <div class="relative w-[500px] h-[500px] flex items-center justify-center">
            <div class="absolute inset-0 bg-white/20 blur-3xl rounded-full scale-110"></div>
            <div class="parallax-item absolute z-20" data-speed="0.05">
                <div class="w-64 h-48 bg-gradient-to-b from-white to-[#ffeef6] rounded-[60px] cloud-body relative flex items-center justify-center">
                    <div class="absolute left-8 top-1/2 w-8 h-4 bg-[#ffb7d5] rounded-full blur-md opacity-60"></div>
                    <div class="absolute right-8 top-1/2 w-8 h-4 bg-[#ffb7d5] rounded-full blur-md opacity-60"></div>
                    <div class="flex flex-col items-center gap-4 mt-4">
                        <div class="flex gap-10">
                            <div class="w-4 h-6 bg-[#2d1622] rounded-full relative overflow-hidden animate-blink"><div class="absolute top-1 right-1 w-1.5 h-1.5 bg-white rounded-full eye-pupil"></div></div>
                            <div class="w-4 h-6 bg-[#2d1622] rounded-full relative overflow-hidden animate-blink"><div class="absolute top-1 right-1 w-1.5 h-1.5 bg-white rounded-full eye-pupil"></div></div>
                        </div>
                        <div class="w-8 h-4 border-b-4 border-[#2d1622] rounded-full"></div>
                    </div>
                </div>
            </div>
            <div class="parallax-item absolute -left-4 top-10 z-10" data-speed="0.08">
                <div class="w-40 h-32 bg-gradient-to-b from-[#f3e7ff] to-[#e0c3fc] rounded-[40px] cloud-body relative flex items-center justify-center opacity-90 transform -rotate-12">
                     <div class="flex flex-col items-center gap-2 mt-2"><div class="flex gap-6"><div class="w-3 h-2 border-t-2 border-[#4a2c3d] rounded-full"></div><div class="w-3 h-2 border-t-2 border-[#4a2c3d] rounded-full"></div></div><div class="w-2 h-2 bg-[#4a2c3d] rounded-full"></div></div>
                </div>
            </div>
             <div class="parallax-item absolute right-0 bottom-10 z-30" data-speed="0.06">
                <div class="w-32 h-24 bg-gradient-to-b from-[#fff5eb] to-[#ffdec2] rounded-[30px] cloud-body relative flex items-center justify-center transform rotate-6">
                    <div class="flex flex-col items-center gap-1 mt-1"><div class="flex gap-4"><div class="w-2 h-2 bg-[#5c3a2e] rounded-full"></div><div class="w-2 h-2 bg-[#5c3a2e] rounded-full"></div></div><div class="w-3 h-1.5 bg-[#5c3a2e] rounded-b-full"></div></div>
                </div>
            </div>
        </div>

        <div class="parallax-item absolute bottom-12 left-12 right-12 text-white z-40" data-speed="0.01">
            <div class="backdrop-blur-sm bg-white/10 p-6 rounded-3xl border border-white/20">
                <div class="flex items-center gap-3 mb-2">
                    <div class="size-8 rounded-full bg-white flex items-center justify-center text-primary shadow-lg"><span class="material-symbols-outlined text-xl">spa</span></div>
                    <span class="text-sm font-bold tracking-wider uppercase text-white drop-shadow-md">Lumina Beauty</span>
                </div>
                <h2 class="text-3xl font-bold leading-tight mb-2 drop-shadow-md">สวัสดีลูกค้าทุกท่าน!</h2>
                <p class="text-base text-white/90 drop-shadow-sm">เข้าร่วมกับเรา และค้นพบผลิตภัณฑ์ที่จะทำให้คุณรู้สึกราวกับล่องลอยอยู่บนปุยเมฆ</p>
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
            
            <div class="flex flex-col gap-8 relative z-10">
                <div class="flex flex-col gap-2">
                    <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-[#1b0d14] dark:text-white">ยินดีต้อนรับกลับมา</h1>
                    <p class="text-[#9a4c73] dark:text-[#dcbccc] text-base">กรุณากรอกข้อมูลเพื่อเข้าสู่ระบบ</p>
                </div>

                <form id="loginForm" action="" class="flex flex-col gap-5 w-full" method="POST" onsubmit="return validateLoginForm(event)">
                    <div class="flex flex-col gap-2 group">
                        <label class="text-sm font-semibold ml-1" for="login_id">อีเมล หรือ ชื่อผู้ใช้</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-[#9a4c73]"><span class="material-symbols-outlined">person</span></div>
                            <input class="w-full pl-11 pr-4 py-3.5 bg-[#fcf8fa] dark:bg-[#1f0e16] border border-[#e7cfdb] rounded-full focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" id="login_id" name="login_id" placeholder="กรอกอีเมล หรือ ชื่อผู้ใช้" type="text"/>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2 group">
                        <label class="text-sm font-semibold ml-1" for="password">รหัสผ่าน</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-[#9a4c73]"><span class="material-symbols-outlined">lock</span></div>
                            <input class="w-full pl-11 pr-4 py-3.5 bg-[#fcf8fa] dark:bg-[#1f0e16] border border-[#e7cfdb] rounded-full focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary" id="password" name="password" placeholder="รหัสผ่าน" type="password"/>
                            <button class="absolute inset-y-0 right-0 pr-4 flex items-center text-[#9a4c73] cursor-pointer hover:text-primary transition-colors" type="button" onclick="togglePassword()">
                                <span id="password-icon" class="material-symbols-outlined">visibility_off</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between px-1">
                        <label class="flex items-center gap-2 cursor-pointer"><input class="h-4 w-4 text-primary rounded border-gray-300" type="checkbox"/><span class="text-sm text-[#5a3a4a] dark:text-[#dcbccc]">จดจำฉัน</span></label>
                        <a class="text-sm font-bold text-primary hover:text-[#c41e6e]" href="#">ลืมรหัสผ่าน?</a>
                    </div>

                    <button type="submit" class="w-full mt-2 bg-primary hover:bg-[#d6207a] text-white font-bold py-3.5 px-6 rounded-full shadow-lg transform active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                        <span>เข้าสู่ระบบ</span>
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </button>
                </form>

                <div class="text-center mt-4">
                    <p class="text-sm text-[#5a3a4a] dark:text-[#dcbccc]">ยังไม่เป็นสมาชิก? <a class="font-bold text-primary hover:text-[#c41e6e]" href="register.php">สมัครสมาชิก</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function validateLoginForm(e) {
        e.preventDefault(); 
        const login_id = document.getElementById('login_id').value.trim();
        const password = document.getElementById('password').value;

        if (!login_id || !password) {
            Swal.fire({
                icon: 'warning',
                title: 'ข้อมูลไม่ครบถ้วน',
                text: 'กรุณากรอกข้อมูลให้ครบถ้วน',
                confirmButtonColor: '#ee2b8c'
            });
            return false;
        }
        document.getElementById('loginForm').submit();
    }

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
        const items = document.querySelectorAll('.parallax-item');
        const centerX = window.innerWidth / 4; 
        const centerY = window.innerHeight / 2;
        const mouseX = e.clientX;
        const mouseY = e.clientY;
        items.forEach(item => {
            const speed = parseFloat(item.getAttribute('data-speed')) || 0.05;
            item.style.transform = `translate(${(mouseX - centerX) * speed}px, ${(mouseY - centerY) * speed}px)`;
        });
        const pupils = document.querySelectorAll('.eye-pupil');
        pupils.forEach(pupil => {
            const eye = pupil.parentElement;
            const rect = eye.getBoundingClientRect();
            const angle = Math.atan2(mouseY - (rect.top + rect.height/2), mouseX - (rect.left + rect.width/2));
            const distance = Math.min(3, Math.hypot(mouseX - (rect.left + rect.width/2), mouseY - (rect.top + rect.height/2)));
            pupil.style.transform = `translate(${Math.cos(angle)*distance}px, ${Math.sin(angle)*distance}px)`;
        });
    });

    // Alert Handling
    <?php if($alertType === 'success'): ?>
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ!',
            text: '<?php echo $alertMsg; ?>',
            timer: 1500,
            showConfirmButton: false
        }).then(() => {
            // เช็ค Session เพื่อ Redirect
            <?php if(isset($_SESSION['admin_id'])): ?>
                window.location.href = '../home.php'; 
            <?php else: ?>
                window.location.href = '../index.php'; 
            <?php endif; ?>
        });
    <?php elseif($alertType === 'error'): ?>
        Swal.fire({
            icon: 'error',
            title: 'พบข้อผิดพลาด',
            text: '<?php echo $alertMsg; ?>', // จะแสดง Error SQL ตรงนี้ถ้ามี
            confirmButtonColor: '#ee2b8c'
        });
    <?php endif; ?>
</script>

</body>
</html>