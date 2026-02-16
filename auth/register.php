<?php
// ประกาศตัวแปรเพื่อเก็บสถานะการแจ้งเตือน
$alertType = "";
$alertMsg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // นำเข้าไฟล์เชื่อมต่อ Database ที่คุณระบุ
    require_once '../config/connectdbuser.php';

    // รับค่าจากแบบฟอร์ม
    $name = $_POST['name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password']; 

    // 📌 เข้ารหัสรหัสผ่านก่อนบันทึกลงฐานข้อมูล (เหมือน manageaccount.php)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // คำสั่ง SQL ตามที่คุณกำหนด (ใส่ NULL ตรง u_id เพื่อให้ Auto Increment ทำงาน)
    $sql = "INSERT INTO `account` (`u_id`, `u_username`, `u_name`, `u_email`, `u_password`) VALUES (NULL, ?, ?, ?, ?)";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        // 📌 ผูกตัวแปร $hashed_password แทน $password ตัวเดิม (s = string)
        mysqli_stmt_bind_param($stmt, "ssss", $username, $name, $email, $hashed_password);
        
        if (mysqli_stmt_execute($stmt)) {
            $alertType = "success";
            $alertMsg = "สร้างบัญชีผู้ใช้ของคุณเรียบร้อยแล้ว!";
        } else {
            $alertType = "error";
            $alertMsg = "เกิดข้อผิดพลาดในการบันทึก: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $alertType = "error";
        $alertMsg = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL";
    }
    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html class="light" lang="th">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>สมัครสมาชิก Lumina Beauty</title>
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
                    animation: {
                        'blink': 'blink 4s infinite',
                        'float': 'float 6s ease-in-out infinite',
                        'float-delayed': 'float 6s ease-in-out 3s infinite',
                        'float-slow': 'float 8s ease-in-out 1s infinite',
                        'tilt': 'tilt 5s ease-in-out infinite',
                    },
                    keyframes: {
                        blink: {
                            '0%, 45%, 55%, 100%': { transform: 'scaleY(1)' },
                            '50%': { transform: 'scaleY(0.1)' },
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-20px)' },
                        },
                        tilt: {
                            '0%, 100%': { transform: 'rotate(-5deg)' },
                            '50%': { transform: 'rotate(5deg)' },
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
                inset -10px -10px 30px rgba(0,0,0,0.05),
                inset 10px 10px 30px rgba(255,255,255,0.9),
                15px 15px 40px rgba(0,0,0,0.08);
        }
        .makeup-brush-handle {
            background: linear-gradient(90deg, #2d1622 0%, #5a2e44 40%, #2d1622 100%);
            box-shadow: 5px 5px 15px rgba(0,0,0,0.2);
        }
        .makeup-brush-bristles {
            background: linear-gradient(to top, #ff9eb5 0%, #ffd1df 100%);
            border-radius: 10px 10px 100px 100px;
        }
        
        .parallax-item { transition: transform 0.1s ease-out; will-change: transform; }
        .eye-pupil { transition: transform 0.05s ease-out; }

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
            background: linear-gradient(45deg, #fff1eb, #ace0f9, #ffffff, #ace0f9, #fff1eb); 
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

        .modal-enter { opacity: 0; transform: scale(0.9); }
        .modal-enter-active { opacity: 1; transform: scale(1); transition: opacity 0.3s, transform 0.3s; }
        .modal-exit { opacity: 1; transform: scale(1); }
        .modal-exit-active { opacity: 0; transform: scale(0.9); transition: opacity 0.3s, transform 0.3s; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-[#1b0d14] dark:text-[#f3e7ed] antialiased min-h-screen flex flex-col relative">

<div class="flex min-h-screen w-full flex-row overflow-hidden">
    
<div id="visual-container" class="hidden lg:flex w-1/2 relative bg-gradient-to-bl from-[#fff1eb] via-[#ace0f9] to-[#fff1eb] items-center justify-center overflow-hidden group/container order-1">

    <div class="parallax-item absolute top-24 left-24 text-white/60 animate-float-delayed" data-speed="0.02">
        <span class="material-symbols-outlined text-5xl text-[#ff9eb5]">star</span>
    </div>
    <div class="parallax-item absolute bottom-40 right-16 text-white/50 animate-float" data-speed="0.03">
        <span class="material-symbols-outlined text-4xl text-[#ff9eb5]">auto_awesome</span>
    </div>

    <div class="relative w-[700px] h-[700px] flex items-center justify-center">
        <div class="parallax-item absolute top-1/4 z-20" data-speed="0.07">
            <div class="w-56 h-40 bg-gradient-to-b from-white to-[#ffeef6] rounded-[50px] cloud-body relative flex items-center justify-center transform hover:scale-105 transition-transform duration-300">
                <div class="absolute left-6 top-1/2 w-6 h-3 bg-[#ffb7d5] rounded-full blur-md opacity-60"></div>
                <div class="absolute right-6 top-1/2 w-6 h-3 bg-[#ffb7d5] rounded-full blur-md opacity-60"></div>
                <div class="flex flex-col items-center gap-3 mt-3">
                    <div class="flex gap-8">
                        <div class="w-3.5 h-5 bg-[#2d1622] rounded-full relative overflow-hidden animate-blink">
                            <div class="absolute top-1 right-0.5 w-1.5 h-1.5 bg-white rounded-full eye-pupil"></div>
                        </div>
                        <div class="w-3.5 h-5 bg-[#2d1622] rounded-full relative overflow-hidden animate-blink">
                            <div class="absolute top-1 right-0.5 w-1.5 h-1.5 bg-white rounded-full eye-pupil"></div>
                        </div>
                    </div>
                    <div class="w-6 h-3 border-b-4 border-[#2d1622] rounded-full"></div>
                </div>
                <div class="absolute -top-6 left-8 w-20 h-20 bg-gradient-to-b from-white to-[#f0f8ff] rounded-full -z-10"></div>
                <div class="absolute -top-2 right-6 w-16 h-16 bg-gradient-to-b from-white to-[#f0f8ff] rounded-full -z-10"></div>
                <div class="absolute top-6 -left-6 w-16 h-16 bg-gradient-to-b from-white to-[#e6f2ff] rounded-full -z-10"></div>
                <div class="absolute top-2 -right-4 w-14 h-14 bg-gradient-to-b from-white to-[#e6f2ff] rounded-full -z-10"></div>
                <div class="absolute -bottom-4 left-16 w-4 h-6 bg-white rounded-full -z-0 border-2 border-[#e6f2ff]"></div>
                <div class="absolute -bottom-4 right-16 w-4 h-6 bg-white rounded-full -z-0 border-2 border-[#e6f2ff]"></div>
            </div>
        </div>

        <div class="parallax-item absolute bottom-1/3 right-20 z-30" data-speed="0.09">
            <div class="w-36 h-28 bg-gradient-to-b from-[#fff5eb] to-[#ffdec2] rounded-[30px] cloud-body relative flex items-center justify-center transform -rotate-12">
                <div class="flex flex-col items-center gap-2 mt-2">
                    <div class="flex gap-5">
                        <div class="w-2.5 h-2.5 bg-[#4a2c3d] rounded-full"></div>
                        <div class="w-2.5 h-2.5 bg-[#4a2c3d] rounded-full"></div>
                    </div>
                    <div class="w-2 h-2 bg-[#ff9e9e] rounded-full blur-[1px]"></div>
                </div>
                <div class="absolute -top-4 right-4 w-12 h-12 bg-[#fff5eb] rounded-full -z-10"></div>
                <div class="absolute top-4 -left-4 w-10 h-10 bg-[#fff5eb] rounded-full -z-10"></div>
            </div>
        </div>

        <div class="parallax-item absolute top-1/3 left-20 z-10" data-speed="0.05">
             <div class="w-28 h-20 bg-gradient-to-b from-[#f3e7ff] to-[#e0c3fc] rounded-[24px] cloud-body relative flex items-center justify-center transform rotate-6 opacity-90">
                <div class="flex flex-col items-center gap-1">
                    <div class="flex gap-3">
                        <div class="w-1.5 h-2 bg-[#1a4731] rounded-full"></div>
                        <div class="w-1.5 h-2 bg-[#1a4731] rounded-full"></div>
                    </div>
                    <div class="w-2 h-1 bg-[#1a4731] rounded-b-full"></div>
                </div>
                <div class="absolute -top-3 left-2 w-10 h-10 bg-[#f3e7ff] rounded-full -z-10"></div>
            </div>
        </div>
    </div>

    <div class="parallax-item absolute bottom-12 left-12 right-12 z-40" data-speed="0.01">
        <div class="backdrop-blur-md bg-white/20 p-6 rounded-3xl border border-white/40 shadow-xl">
            <div class="flex items-center gap-3 mb-2">
                <div class="size-8 rounded-full bg-white flex items-center justify-center text-primary shadow-sm">
                    <span class="material-symbols-outlined text-xl">auto_awesome</span>
                </div>
                <span class="text-sm font-bold tracking-wider uppercase text-[#2d1622]/80">สมัครสมาชิกสุดวิเศษ</span>
            </div>
            <h2 class="text-3xl font-bold leading-tight mb-2 text-[#2d1622]">เปล่งประกายไปด้วยกัน!</h2>
            <p class="text-base text-[#2d1622]/80">สร้างบัญชีเพื่อปลดล็อกเคล็ดลับความงามและสิทธิพิเศษมากมาย</p>
        </div>
    </div>
</div>

<div class="flex w-full lg:w-1/2 flex-col items-center justify-center p-6 md:p-12 lg:p-24 bg-surface-light dark:bg-surface-dark relative order-2">
    <div class="lg:hidden absolute top-6 left-6 flex items-center gap-2 text-primary">
        <span class="material-symbols-outlined text-3xl">spa</span>
        <span class="text-xl font-bold tracking-tight text-[#1b0d14] dark:text-white">Lumina</span>
    </div>
    
    <div class="w-full max-w-xl gradient-border-box p-10 md:p-16 z-10 relative">
        <div class="flex flex-col gap-2 mb-6">
            <div class="hidden lg:flex items-center gap-2 text-primary mb-2">
                <span class="material-symbols-outlined text-4xl">spa</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-[#1b0d14] dark:text-white">สร้างบัญชีผู้ใช้</h1>
            <p class="text-[#9a4c73] dark:text-[#dcbccc] text-base">เข้าร่วมกับ Lumina ได้แล้ววันนี้</p>
        </div>

        <form id="signupForm" action="" class="flex flex-col gap-5 w-full" method="POST" novalidate onsubmit="return validateForm(event)">
            
            <div class="flex flex-col gap-2 group">
                <label class="text-sm font-semibold text-[#1b0d14] dark:text-[#f3e7ed] ml-1" for="name">ชื่อ-นามสกุล</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-[#9a4c73]">
                        <span class="material-symbols-outlined text-[20px]">person</span>
                    </div>
                    <input class="w-full pl-11 pr-4 py-4 bg-[#fcf8fa] dark:bg-[#1f0e16] border border-[#e7cfdb] dark:border-[#5a3a4a] rounded-full text-[#1b0d14] dark:text-white placeholder:text-[#9a4c73]/50 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200" id="name" name="name" placeholder="สมหญิง รักสวย" type="text"/>
                </div>
            </div>

            <div class="flex flex-col gap-2 group">
                <label class="text-sm font-semibold text-[#1b0d14] dark:text-[#f3e7ed] ml-1" for="username">ชื่อผู้ใช้ (Username)</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-[#9a4c73]">
                        <span class="material-symbols-outlined text-[20px]">badge</span>
                    </div>
                    <input class="w-full pl-11 pr-4 py-4 bg-[#fcf8fa] dark:bg-[#1f0e16] border border-[#e7cfdb] dark:border-[#5a3a4a] rounded-full text-[#1b0d14] dark:text-white placeholder:text-[#9a4c73]/50 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200" id="username" name="username" placeholder="ตั้งชื่อผู้ใช้สำหรับการเข้าระบบ" type="text"/>
                </div>
            </div>

            <div class="flex flex-col gap-2 group">
                <label class="text-sm font-semibold text-[#1b0d14] dark:text-[#f3e7ed] ml-1" for="email">อีเมล</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-[#9a4c73]">
                        <span class="material-symbols-outlined text-[20px]">mail</span>
                    </div>
                    <input class="w-full pl-11 pr-4 py-4 bg-[#fcf8fa] dark:bg-[#1f0e16] border border-[#e7cfdb] dark:border-[#5a3a4a] rounded-full text-[#1b0d14] dark:text-white placeholder:text-[#9a4c73]/50 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200" id="email" name="email" placeholder="yourname@example.com" type="email"/>
                </div>
            </div>
            
            <div class="flex flex-col gap-2 group">
                <label class="text-sm font-semibold text-[#1b0d14] dark:text-[#f3e7ed] ml-1" for="password">รหัสผ่าน</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-[#9a4c73]">
                        <span class="material-symbols-outlined text-[20px]">lock</span>
                    </div>
                    <input class="w-full pl-11 pr-4 py-4 bg-[#fcf8fa] dark:bg-[#1f0e16] border border-[#e7cfdb] dark:border-[#5a3a4a] rounded-full text-[#1b0d14] dark:text-white placeholder:text-[#9a4c73]/50 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200" id="password" name="password" placeholder="สร้างรหัสผ่านที่ปลอดภัย" type="password"/>
                    <button class="absolute inset-y-0 right-0 pr-4 flex items-center text-[#9a4c73] hover:text-primary transition-colors cursor-pointer" type="button" onclick="togglePassword('password', 'icon-pass')">
                        <span id="icon-pass" class="material-symbols-outlined text-[20px]">visibility_off</span>
                    </button>
                </div>
            </div>
            
            <div class="flex flex-col gap-2 group">
                <label class="text-sm font-semibold text-[#1b0d14] dark:text-[#f3e7ed] ml-1" for="confirm_password">ยืนยันรหัสผ่าน</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-[#9a4c73]">
                        <span class="material-symbols-outlined text-[20px]">lock</span>
                    </div>
                    <input class="w-full pl-11 pr-4 py-4 bg-[#fcf8fa] dark:bg-[#1f0e16] border border-[#e7cfdb] dark:border-[#5a3a4a] rounded-full text-[#1b0d14] dark:text-white placeholder:text-[#9a4c73]/50 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200" id="confirm_password" name="confirm_password" placeholder="ยืนยันรหัสผ่านของคุณ" type="password"/>
                    <button class="absolute inset-y-0 right-0 pr-4 flex items-center text-[#9a4c73] hover:text-primary transition-colors cursor-pointer" type="button" onclick="togglePassword('confirm_password', 'icon-confirm')">
                        <span id="icon-confirm" class="material-symbols-outlined text-[20px]">visibility_off</span>
                    </button>
                </div>
            </div>
            
            <div class="flex items-center px-1">
                <label class="flex items-center gap-2 cursor-pointer group">
                    <div class="relative flex items-center">
                        <input id="terms_agree" class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-[#e7cfdb] dark:border-[#5a3a4a] bg-transparent checked:bg-primary checked:border-primary transition-all" type="checkbox"/>
                        <span class="material-symbols-outlined absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-[16px] text-white opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity">check</span>
                    </div>
                    <span class="text-sm font-medium text-[#5a3a4a] dark:text-[#dcbccc] group-hover:text-primary transition-colors">ฉันยอมรับ <a class="underline decoration-primary/50 hover:decoration-primary text-primary" href="javascript:void(0)" onclick="openModal()">เงื่อนไขและข้อตกลง</a></span>
                </label>
            </div>
            
            <button type="submit" class="w-full mt-2 bg-primary hover:bg-[#d6207a] text-white text-base font-bold py-3.5 px-6 rounded-full shadow-lg shadow-primary/30 transform active:scale-[0.98] transition-all duration-200 flex items-center justify-center gap-2">
                <span>สร้างบัญชี</span>
                <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
            </button>
        </form>
        <div class="text-center mt-4">
            <p class="text-sm text-[#5a3a4a] dark:text-[#dcbccc]">
                มีบัญชีอยู่แล้ว? 
                <a class="font-bold text-primary hover:text-[#c41e6e] transition-colors" href="login.php">เข้าสู่ระบบ</a>
            </p>
        </div>
    </div>
    <div class="absolute bottom-0 left-0 p-8 hidden lg:block pointer-events-none opacity-20">
        <span class="material-symbols-outlined text-[100px] text-primary -rotate-12">face_3</span>
    </div>
</div>

</div>

<div id="termsModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 transition-opacity backdrop-blur-sm" onclick="closeModal()"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg modal-enter" id="modalContent">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-[#fff1eb] sm:mx-0 sm:h-10 sm:w-10">
                            <span class="material-symbols-outlined text-primary">gavel</span>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg font-semibold leading-6 text-gray-900" id="modal-title">เงื่อนไขและข้อตกลง</h3>
                            <div class="mt-4 max-h-60 overflow-y-auto pr-2">
                                <p class="text-sm text-gray-500 mb-2">ยินดีต้อนรับสู่ Lumina Beauty โปรดอ่านข้อกำหนดเหล่านี้อย่างละเอียดก่อนใช้งาน:</p>
                                <ul class="list-disc pl-5 text-sm text-gray-500 space-y-2 text-left">
                                    <li><strong>การใช้งานข้อมูล:</strong> ข้อมูลส่วนบุคคลของท่านจะถูกเก็บรักษาเป็นความลับตามนโยบายความเป็นส่วนตัว</li>
                                    <li><strong>ความถูกต้องของข้อมูล:</strong> ท่านรับรองว่าข้อมูลที่ใช้สมัครสมาชิกเป็นความจริงทุกประการ</li>
                                    <li><strong>ลิขสิทธิ์:</strong> เนื้อหาและรูปภาพทั้งหมดในเว็บไซต์เป็นลิขสิทธิ์ของ Lumina Beauty ห้ามคัดลอกโดยไม่ได้รับอนุญาต</li>
                                    <li><strong>การยกเลิกสมาชิก:</strong> เราขอสงวนสิทธิ์ในการระงับบัญชีหากพบการใช้งานที่ผิดปกติ</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                    <button type="button" class="inline-flex w-full justify-center rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#d6207a] sm:ml-3 sm:w-auto transition-colors" onclick="closeModal()">ยอมรับ</button>
                    <button type="button" class="mt-3 inline-flex w-full justify-center rounded-full bg-white px-5 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto transition-colors" onclick="closeModal()">ปิด</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // สคริปต์แจ้งเตือนแบบสวยงามและตรวจสอบฟอร์มก่อนส่ง
    function validateForm(e) {
        e.preventDefault(); // ระงับการส่งฟอร์มไว้ชั่วคราวเพื่อตรวจสอบก่อน

        // ดึงค่าจากช่องต่างๆ
        const name = document.getElementById('name').value.trim();
        const username = document.getElementById('username').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirm_password = document.getElementById('confirm_password').value;
        const terms = document.getElementById('terms_agree').checked;

        // แจ้งเตือนเมื่อข้อมูลไม่ครบ
        if (!name || !username || !email || !password || !confirm_password) {
            Swal.fire({
                icon: 'warning',
                title: 'ข้อมูลไม่ครบถ้วน',
                text: 'กรุณากรอกข้อมูลให้ครบทุกช่องค่ะ',
                confirmButtonColor: '#ee2b8c'
            });
            return false;
        }

        // แจ้งเตือนเมื่อรหัสผ่านไม่ตรงกัน
        if (password !== confirm_password) {
            Swal.fire({
                icon: 'error',
                title: 'รหัสผ่านไม่ตรงกัน',
                text: 'กรุณายืนยันรหัสผ่านใหม่อีกครั้งค่ะ',
                confirmButtonColor: '#ee2b8c'
            });
            return false;
        }

        // แจ้งเตือนเมื่อไม่กดยอมรับเงื่อนไข
        if (!terms) {
            Swal.fire({
                icon: 'info',
                title: 'ข้อตกลงและเงื่อนไข',
                text: 'กรุณากดยอมรับเงื่อนไขและข้อตกลงก่อนสมัครสมาชิกค่ะ',
                confirmButtonColor: '#ee2b8c'
            });
            return false;
        }

        // หากข้อมูลถูกต้องครบถ้วนแล้ว ให้ Submit ฟอร์มได้
        document.getElementById('signupForm').submit();
    }

    // แสดงแจ้งเตือนกรณีที่ส่งฟอร์มไปประมวลผลที่ PHP แล้ว (สำเร็จ / ผิดพลาด)
    <?php if($alertType === 'success'): ?>
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ!',
            text: '<?php echo $alertMsg; ?>',
            confirmButtonColor: '#ee2b8c'
        }).then(() => {
            // เมื่อกดตกลง ให้เปลี่ยนหน้าไปหน้า login
            window.location.href = 'login.php'; 
        });
    <?php elseif($alertType === 'error'): ?>
        Swal.fire({
            icon: 'error',
            title: 'ข้อผิดพลาด!',
            text: '<?php echo $alertMsg; ?>',
            confirmButtonColor: '#ee2b8c'
        });
    <?php endif; ?>

    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        
        if (input.type === "password") {
            input.type = "text";
            icon.textContent = "visibility";
        } else {
            input.type = "password";
            icon.textContent = "visibility_off";
        }
    }

    document.addEventListener("mousemove", (e) => {
        const visualContainer = document.getElementById('visual-container');
        if (window.innerWidth < 1024) return;

        const items = document.querySelectorAll('.parallax-item');
        
        const centerX = window.innerWidth * 0.25; 
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
            const distance = Math.min(2, Math.hypot(mouseX - eyeCenterX, mouseY - eyeCenterY));
            
            const moveX = Math.cos(angle) * distance;
            const moveY = Math.sin(angle) * distance;
            
            pupil.style.transform = `translate(${moveX}px, ${moveY}px)`;
        });
    });

    function openModal() {
        const modal = document.getElementById('termsModal');
        const content = document.getElementById('modalContent');
        modal.classList.remove('hidden');
        setTimeout(() => {
            content.classList.remove('modal-enter');
            content.classList.add('modal-enter-active');
        }, 10);
    }

    function closeModal() {
        const modal = document.getElementById('termsModal');
        const content = document.getElementById('modalContent');
        
        content.classList.remove('modal-enter-active');
        content.classList.add('modal-exit-active');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            content.classList.remove('modal-exit-active');
            content.classList.add('modal-enter'); 
        }, 300); 
    }
</script>
</body>
</html>