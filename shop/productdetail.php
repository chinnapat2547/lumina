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
            $totalCartItems = $rowCartCount['total_qty'] ?? 0;
        }
        mysqli_stmt_close($stmtCartCount);
    }
}

// ==========================================
// 2. ดึงข้อมูลสินค้าที่ระบุตาม ID
// ==========================================
$p_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ถ้าไม่มี ID ให้เด้งกลับไปหน้าสินค้าทั้งหมด
if ($p_id <= 0) {
    header("Location: products.php");
    exit();
}

$product = null;
$sqlProduct = "SELECT * FROM `product` WHERE p_id = ?";
if ($stmt = mysqli_prepare($conn, $sqlProduct)) {
    mysqli_stmt_bind_param($stmt, "i", $p_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// ถ้าหาสินค้าไม่เจอ เด้งกลับ
if (!$product) {
    header("Location: products.php");
    exit();
}

// ==========================================
// 3. ดึงรูปภาพแกลเลอรีของสินค้านี้
// ==========================================
$images = [];
// เอารูปหลัก (จากตาราง product) มาใส่เป็นรูปแรกก่อน
$mainImg = (!empty($product['p_image']) && file_exists("../uploads/products/" . $product['p_image'])) 
            ? "../uploads/products/" . $product['p_image'] 
            : "https://via.placeholder.com/600x600.png?text=No+Image";
$images[] = $mainImg;

// ดึงรูปเพิ่มเติมจากตาราง product_images
$sqlImages = "SELECT image_name FROM `product_images` WHERE p_id = ?";
if ($stmtImg = mysqli_prepare($conn, $sqlImages)) {
    mysqli_stmt_bind_param($stmtImg, "i", $p_id);
    mysqli_stmt_execute($stmtImg);
    $resultImg = mysqli_stmt_get_result($stmtImg);
    while ($rowImg = mysqli_fetch_assoc($resultImg)) {
        $imgPath = "../uploads/products/" . $rowImg['image_name'];
        if (file_exists($imgPath) && $imgPath !== $mainImg) {
            $images[] = $imgPath;
        }
    }
    mysqli_stmt_close($stmtImg);
}

// ==========================================
// 4. ดึงสินค้าแนะนำ (สุ่มมา 4 รายการ)
// ==========================================
$recommended = [];
$sqlRec = "SELECT p_id, p_name, p_price, p_image, p_detail FROM `product` WHERE p_id != ? ORDER BY RAND() LIMIT 4";
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
              "pastel-pink": "#FFE4E6",
              "pastel-blue": "#E0E7FF",
              "pastel-purple": "#F3E8FF",
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
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .dark .glass-panel {
            background: rgba(45, 38, 53, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        /* สไตล์เอาลูกศรตัวเลขใน input ออก */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
          -webkit-appearance: none; margin: 0; 
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-pink-50 dark:from-background-dark dark:to-gray-900 min-h-screen text-gray-800 dark:text-gray-100 font-display transition-colors duration-300">

<header class="sticky top-0 z-50 glass-panel shadow-sm px-6 py-4 mb-8 relative z-50">
    <div class="w-full px-6 md:px-10 lg:px-16"> 
        <div class="flex justify-between items-center h-20 w-full">
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

            <a href="favorites.php" class="text-gray-500 dark:text-gray-300 hover:text-pink-600 transition relative flex items-center justify-center">
                <span class="material-icons-round text-2xl">favorite_border</span>
            </a>
            <a href="cart.php" class="relative w-10 h-10 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-800 rounded-full transition-all cursor-pointer">
                <span class="material-icons-round text-2xl">shopping_bag</span>
                <span class="absolute -top-1.5 -right-2 bg-primary text-white text-[10px] font-bold rounded-full h-[18px] w-[18px] flex items-center justify-center border-2 border-white dark:border-gray-800"><?= $totalCartItems ?></span>
            </a>
            <button class="w-10 h-10 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-800 rounded-full transition-all" onclick="toggleTheme()">
                <span class="material-icons-round dark:hidden text-2xl">dark_mode</span>
                <span class="material-icons-round hidden dark:block text-yellow-400 text-2xl">light_mode</span>
            </button>
            
            <div class="relative group flex items-center">
                <a href="../profile/account.php" class="block w-10 h-10 rounded-full bg-gradient-to-tr from-pink-300 to-purple-300 p-0.5 shadow-sm hover:shadow-md hover:scale-105 transition-all cursor-pointer">
                    <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-full h-full">
                        <img alt="Profile" class="w-full h-full rounded-full object-cover" src="<?= htmlspecialchars($profileImage) ?>" onerror="this.src='https://ui-avatars.com/api/?name=User&background=F43F85&color=fff'"/>
                    </div>
                </a>
            </div>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
    <nav class="flex mb-8 text-sm text-gray-500 dark:text-gray-400">
        <a class="hover:text-primary transition-colors" href="../home.php">หน้าหลัก</a>
        <span class="mx-2">/</span>
        <a class="hover:text-primary transition-colors" href="products.php">สินค้าทั้งหมด</a>
        <span class="mx-2">/</span>
        <span class="text-primary font-medium"><?= htmlspecialchars($product['p_name']) ?></span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-20 items-start">
        
        <div class="relative group">
            <div class="bg-white dark:bg-gray-800 rounded-[3rem] p-4 lg:p-8 shadow-soft border border-primary/10 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-6 z-10">
                    <span class="bg-primary text-white text-xs font-bold px-3 py-1 rounded-full">New</span>
                </div>
                <div class="w-full aspect-square flex items-center justify-center">
                    <img id="mainImage" alt="<?= htmlspecialchars($product['p_name']) ?>" class="w-full h-full object-cover rounded-2xl transform transition duration-500" src="<?= htmlspecialchars($images[0]) ?>"/>
                </div>
            </div>
            
            <?php if (count($images) > 1): ?>
            <div class="flex gap-4 mt-6 justify-center overflow-x-auto hide-scrollbar pb-2">
                <?php foreach ($images as $index => $img): ?>
                    <button type="button" onclick="changeMainImage(this, '<?= htmlspecialchars($img) ?>')" class="thumbnail-btn w-20 h-20 flex-shrink-0 rounded-2xl border-2 <?= $index == 0 ? 'border-primary' : 'border-transparent hover:border-primary/50 opacity-70 hover:opacity-100' ?> overflow-hidden bg-white transition-all">
                        <img alt="Thumbnail" class="w-full h-full object-cover" src="<?= htmlspecialchars($img) ?>"/>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="flex flex-col gap-6">
            <div>
                <p class="text-sm font-bold text-primary tracking-widest mb-1 uppercase"><?= htmlspecialchars($product['p_category'] ?? 'BEAUTY') ?></p>
                <h1 class="text-3xl lg:text-4xl font-bold text-gray-900 dark:text-white leading-snug mb-2">
                    <?= htmlspecialchars($product['p_name']) ?>
                </h1>
            </div>

            <div class="flex items-baseline gap-3">
                <span class="text-4xl font-bold text-primary font-display">฿<?= number_format($product['p_price']) ?></span>
            </div>

            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-primary/10 shadow-sm">
                <p class="text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($product['p_detail']) ?></p>
            </div>

            <form action="cart.php" method="POST" class="pt-4 border-t border-gray-100 dark:border-gray-800 space-y-6">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="p_id" value="<?= $p_id ?>">
                
                <div class="flex items-center gap-4">
                    <span class="text-gray-700 dark:text-gray-200 font-medium">จำนวน:</span>
                    <div class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-full p-1 border border-gray-200 dark:border-gray-600">
                        <button type="button" onclick="adjustQty(-1)" class="w-8 h-8 rounded-full bg-white dark:bg-gray-600 shadow-sm flex items-center justify-center text-gray-600 dark:text-white hover:text-primary transition">
                            <span class="material-icons-round text-sm">remove</span>
                        </button>
                        <input type="number" name="qty" id="qtyInput" value="1" min="1" max="99" class="w-12 text-center font-bold text-gray-900 dark:text-white bg-transparent border-none outline-none focus:ring-0 p-0">
                        <button type="button" onclick="adjustQty(1)" class="w-8 h-8 rounded-full bg-white dark:bg-gray-600 shadow-sm flex items-center justify-center text-gray-600 dark:text-white hover:text-primary transition">
                            <span class="material-icons-round text-sm">add</span>
                        </button>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-4">
                    <button type="submit" class="flex-1 bg-gradient-to-r from-primary to-pink-500 hover:shadow-glow text-white font-bold py-4 px-8 rounded-full transition transform hover:-translate-y-1 shadow-lg flex items-center justify-center gap-2">
                        <span class="material-icons-round">shopping_cart</span>
                        เพิ่มลงตะกร้า
                    </button>
            </form>
            
            <form action="favorites.php" method="POST" class="sm:w-auto">
                <input type="hidden" name="action" value="add_fav">
                <input type="hidden" name="p_id" value="<?= $p_id ?>">
                <button type="submit" class="w-full h-full p-4 rounded-full border-2 border-gray-200 dark:border-gray-700 text-gray-400 hover:text-primary hover:border-primary transition flex items-center justify-center group" title="เพิ่มลงสิ่งที่ถูกใจ">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">favorite</span>
                </button>
            </form>
                </div>
        </div>
    </div>

    <?php if (count($recommended) > 0): ?>
    <div class="mt-24 border-t border-primary/10 pt-16">
        <div class="flex justify-between items-end mb-8">
            <div>
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">สินค้าที่คุณอาจชอบ</h2>
                <p class="text-gray-500 mt-2">ผลิตภัณฑ์ความงามอื่นๆ ที่เข้ากับคุณ</p>
            </div>
            <a class="text-primary hover:text-pink-600 font-medium flex items-center gap-1 transition-colors" href="products.php">
                ดูทั้งหมด <span class="material-icons-round text-lg">arrow_forward</span>
            </a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6 pb-8">
            <?php foreach($recommended as $rec): 
                $recImg = (!empty($rec['p_image']) && file_exists("../uploads/products/" . $rec['p_image'])) 
                            ? "../uploads/products/" . $rec['p_image'] 
                            : "https://via.placeholder.com/400x400.png?text=No+Image";
            ?>
            <a href="product_detail.php?id=<?= $rec['p_id'] ?>" class="bg-white dark:bg-surface-dark rounded-3xl p-3 sm:p-4 shadow-sm hover:shadow-glow transition duration-300 group border border-gray-100 dark:border-gray-700 block relative">
                <div class="relative rounded-2xl overflow-hidden aspect-square mb-4 bg-gray-50 dark:bg-gray-800 flex items-center justify-center">
                    <img alt="<?= htmlspecialchars($rec['p_name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" src="<?= $recImg ?>"/>
                </div>
                <h3 class="font-bold text-gray-800 dark:text-white text-md mb-1 line-clamp-1"><?= htmlspecialchars($rec['p_name']) ?></h3>
                <p class="text-xs text-gray-500 mb-3 line-clamp-1"><?= htmlspecialchars($rec['p_detail']) ?></p>
                <div class="flex justify-between items-center">
                    <span class="text-primary font-bold text-lg">฿<?= number_format($rec['p_price']) ?></span>
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

    // ฟังก์ชันปรับจำนวนสินค้า (+/-)
    function adjustQty(amount) {
        const input = document.getElementById('qtyInput');
        let currentVal = parseInt(input.value) || 1;
        let newVal = currentVal + amount;
        if (newVal >= 1 && newVal <= 99) {
            input.value = newVal;
        }
    }

    // ฟังก์ชันเปลี่ยนรูปหลักเมื่อกด Thumbnail
    function changeMainImage(clickedBtn, imgSrc) {
        // 1. เปลี่ยน src ของรูปใหญ่
        document.getElementById('mainImage').src = imgSrc;
        
        // 2. ลบกรอบสีชมพูออกจากปุ่ม Thumbnail ทุกอัน
        const btns = document.querySelectorAll('.thumbnail-btn');
        btns.forEach(btn => {
            btn.classList.remove('border-primary', 'opacity-100');
            btn.classList.add('border-transparent', 'opacity-70');
        });
        
        // 3. ใส่กรอบสีชมพูให้ปุ่มที่ถูกกด
        clickedBtn.classList.remove('border-transparent', 'opacity-70');
        clickedBtn.classList.add('border-primary', 'opacity-100');
    }
</script>
</body></html>