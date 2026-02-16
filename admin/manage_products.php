<?php
session_start();
require_once '../config/connectdbuser.php';

// ดึงหมวดหมู่
$categories = [];
$resCat = mysqli_query($conn, "SELECT * FROM category");
while($c = mysqli_fetch_assoc($resCat)) { $categories[] = $c; }

// ==========================================
// 1. จัดการเพิ่มสินค้า (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_product') {
    $name = $_POST['p_name'];
    $sku = $_POST['p_sku'];
    $cat_id = $_POST['c_id'];
    $price = $_POST['p_price'];
    $stock = $_POST['p_stock'];
    $detail = $_POST['p_detail'];

    $sql = "INSERT INTO product (p_name, p_sku, c_id, p_price, p_stock, p_detail) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssidis", $name, $sku, $cat_id, $price, $stock, $detail);
    
    if (mysqli_stmt_execute($stmt)) {
        $product_id = mysqli_insert_id($conn);

        // อัปโหลดรูปหน้าปก
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
            $ext = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
            $main_img_name = "prod_" . $product_id . "_main." . $ext;
            // 🛑 ต้องมั่นใจว่ามีโฟลเดอร์ uploads/products แล้ว
            move_uploaded_file($_FILES['main_image']['tmp_name'], "../uploads/products/" . $main_img_name);
            mysqli_query($conn, "UPDATE product SET p_image = '$main_img_name' WHERE p_id = $product_id");
        }

        // อัปโหลดรูป Gallery
        if (isset($_FILES['gallery_images'])) {
            foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['gallery_images']['error'][$key] == 0) {
                    $ext = pathinfo($_FILES['gallery_images']['name'][$key], PATHINFO_EXTENSION);
                    $gall_img_name = "prod_" . $product_id . "_gall_" . time() . "_" . $key . "." . $ext;
                    if (move_uploaded_file($tmp_name, "../uploads/products/" . $gall_img_name)) {
                        mysqli_query($conn, "INSERT INTO product_images (p_id, image_url) VALUES ($product_id, '$gall_img_name')");
                    }
                }
            }
        }

        // เพิ่มสี
        if (isset($_POST['color_names']) && isset($_POST['color_hexes'])) {
            $c_names = $_POST['color_names'];
            $c_hexes = $_POST['color_hexes'];
            for ($i = 0; $i < count($c_names); $i++) {
                if (!empty($c_names[$i])) {
                    $c_name = mysqli_real_escape_string($conn, $c_names[$i]);
                    $c_hex = mysqli_real_escape_string($conn, $c_hexes[$i]);
                    mysqli_query($conn, "INSERT INTO product_colors (p_id, color_name, color_hex) VALUES ($product_id, '$c_name', '$c_hex')");
                }
            }
        }
        
        $_SESSION['success_msg'] = "เพิ่มสินค้าสำเร็จ!";
    } else {
        $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการบันทึก";
    }
    header("Location: manage_products.php");
    exit();
}

// ==========================================
// 2. จัดการแก้ไขสินค้าเบื้องต้น (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_product') {
    $p_id = (int)$_POST['edit_p_id'];
    $name = $_POST['p_name'];
    $sku = $_POST['p_sku'];
    $cat_id = $_POST['c_id'];
    $price = $_POST['p_price'];
    $stock = $_POST['p_stock'];
    $detail = $_POST['p_detail'];

    $sql = "UPDATE product SET p_name=?, p_sku=?, c_id=?, p_price=?, p_stock=?, p_detail=? WHERE p_id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssidis", $name, $sku, $cat_id, $price, $stock, $detail, $p_id);
    mysqli_stmt_execute($stmt);

    // ถ้ามีการอัปโหลดรูปปกใหม่ ให้เปลี่ยนรูป
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
        $ext = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
        $main_img_name = "prod_" . $p_id . "_main_" . time() . "." . $ext;
        move_uploaded_file($_FILES['main_image']['tmp_name'], "../uploads/products/" . $main_img_name);
        mysqli_query($conn, "UPDATE product SET p_image = '$main_img_name' WHERE p_id = $p_id");
    }

    $_SESSION['success_msg'] = "แก้ไขข้อมูลสินค้าสำเร็จ!";
    header("Location: manage_products.php");
    exit();
}

// ==========================================
// 3. จัดการเปิด/ปิดสถานะ (GET)
// ==========================================
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $t_id = (int)$_GET['id'];
    $current = (int)$_GET['toggle_status'];
    $new_status = $current === 1 ? 0 : 1; // สลับ 1 เป็น 0, 0 เป็น 1
    mysqli_query($conn, "UPDATE product SET status = $new_status WHERE p_id = $t_id");
    
    $_SESSION['success_msg'] = "อัปเดตสถานะสำเร็จ!";
    header("Location: manage_products.php");
    exit();
}

// ==========================================
// 4. จัดการลบสินค้า (GET)
// ==========================================
if(isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM product WHERE p_id = $del_id");
    mysqli_query($conn, "DELETE FROM product_images WHERE p_id = $del_id");
    mysqli_query($conn, "DELETE FROM product_colors WHERE p_id = $del_id");
    $_SESSION['success_msg'] = "ลบสินค้าสำเร็จ!";
    header("Location: manage_products.php");
    exit();
}

// สถิติ
$stat_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM product"))['c'];
$stat_out = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM product WHERE p_stock = 0"))['c'];
$stat_low = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM product WHERE p_stock > 0 AND p_stock <= 10"))['c'];

// โชว์แจ้งเตือนออเดอร์
$today = date('Y-m-d');
$newOrders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(order_id) as c FROM orders WHERE DATE(created_at) = '$today'"))['c'] ?? 0;

// ดึงสินค้า
$products = [];
$resProd = mysqli_query($conn, "SELECT p.*, c.c_name FROM product p LEFT JOIN category c ON p.c_id = c.c_id ORDER BY p.p_id DESC");
while($p = mysqli_fetch_assoc($resProd)) { $products[] = $p; }
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>จัดการสินค้า - Lumina Beauty Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: { primary: "#F43F85", "primary-light": "#fce7f3", "background-light": "#FFF5F7", "card-light": "#FFFFFF", "text-main": "#374151" },
                fontFamily: { display: ["Prompt", "sans-serif"], body: ["Prompt", "sans-serif"] },
                borderRadius: { DEFAULT: "1rem", "xl": "1.5rem", "2xl": "2rem" },
                boxShadow: { "soft": "0 10px 40px -10px rgba(244, 63, 133, 0.15)" }
            },
        },
    }
</script>
<style>
    body { font-family: 'Prompt', sans-serif; }
    .glass-panel { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(244, 63, 133, 0.1); }
    .nav-item-active { background-color: #F43F85; color: white; box-shadow: 0 4px 12px rgba(244, 63, 133, 0.3); }
    .nav-item:hover:not(.nav-item-active) { background-color: #fce7f3; color: #F43F85; }
    .color-scroll::-webkit-scrollbar { width: 4px; }
    .color-scroll::-webkit-scrollbar-thumb { background: #fce7f3; border-radius: 4px; }
</style>
</head>
<body class="bg-background-light text-text-main overflow-x-hidden">
<div class="flex min-h-screen w-full">
    
    <aside class="hidden lg:flex flex-col w-72 h-screen sticky top-0 border-r border-primary/10 bg-white p-6 justify-between z-20">
        <div>
            <div class="flex items-center gap-3 px-2 mb-10">
                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                    <span class="material-icons-round text-3xl">spa</span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-primary font-display">Lumina Admin</h1>
            </div>
            <nav class="flex flex-col gap-2">
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-gray-500 transition-all duration-300" href="dashboard.php">
                    <span class="material-icons-round">dashboard</span><span class="font-medium text-sm">ภาพรวม</span>
                </a>
                <a class="nav-item-active flex items-center gap-4 px-5 py-3.5 rounded-2xl transition-all duration-300" href="#">
                    <span class="material-icons-round">inventory_2</span><span class="font-bold text-sm">จัดการสินค้า</span>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-gray-500 transition-all duration-300" href="manage_orders.php">
                    <span class="material-icons-round">receipt_long</span><span class="font-medium text-sm">รายการสั่งซื้อ</span>
                    <?php if($newOrders > 0): ?>
                        <span class="ml-auto bg-primary text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $newOrders ?></span>
                    <?php endif; ?>
                </a>
            </nav>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0">
        <header class="flex items-center justify-between px-6 py-4 lg:px-10 lg:py-6 glass-panel sticky top-0 z-10 border-b border-white/50">
            <div class="hidden md:flex flex-1 max-w-md relative group">
                <span class="material-icons-round absolute left-4 top-2.5 text-gray-400">search</span>
                <input class="block w-full pl-12 pr-3 py-2.5 rounded-full border-none bg-white shadow-sm text-sm placeholder-gray-400 focus:ring-2 focus:ring-primary/20 outline-none" placeholder="ค้นหาสินค้า (ชื่อ, SKU)..." type="text"/>
            </div>
            <div class="flex items-center gap-4 ml-auto">
                <img src="https://ui-avatars.com/api/?name=Admin&background=F43F85&color=fff" class="w-10 h-10 rounded-full shadow-sm">
            </div>
        </header>

        <div class="p-6 lg:p-10 flex flex-col gap-8 max-w-[1600px] mx-auto w-full">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-800">จัดการสินค้า</h1>
                </div>
                <button onclick="openModal('addProductModal')" class="bg-primary hover:bg-pink-600 text-white px-6 py-3 rounded-full font-bold text-sm shadow-lg shadow-primary/30 flex items-center gap-2 transition-transform transform hover:-translate-y-1">
                    <span class="material-icons-round">add</span> เพิ่มสินค้าใหม่
                </button>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
                <div class="bg-white p-5 rounded-2xl shadow-soft border border-pink-50 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-pink-50 flex items-center justify-center text-primary"><span class="material-icons-round text-2xl">inventory_2</span></div>
                    <div><p class="text-gray-500 text-xs font-bold">สินค้าทั้งหมด</p><h3 class="text-2xl font-bold text-gray-800"><?= $stat_total ?></h3></div>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-soft border border-pink-50 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center text-red-500"><span class="material-icons-round text-2xl">production_quantity_limits</span></div>
                    <div><p class="text-gray-500 text-xs font-bold">สินค้าที่หมด</p><h3 class="text-2xl font-bold text-gray-800"><?= $stat_out ?></h3></div>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-soft border border-pink-50 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-yellow-50 flex items-center justify-center text-yellow-600"><span class="material-icons-round text-2xl">low_priority</span></div>
                    <div><p class="text-gray-500 text-xs font-bold">สินค้าใกล้หมด</p><h3 class="text-2xl font-bold text-gray-800"><?= $stat_low ?></h3></div>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-soft overflow-hidden border border-pink-50">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                                <th class="px-6 py-4 font-bold pl-8">รูปภาพ</th>
                                <th class="px-6 py-4 font-bold">ชื่อสินค้า / SKU</th>
                                <th class="px-6 py-4 font-bold">หมวดหมู่</th>
                                <th class="px-6 py-4 font-bold">ราคา</th>
                                <th class="px-6 py-4 font-bold text-center">สต็อก</th>
                                <th class="px-6 py-4 font-bold text-center">สถานะ</th>
                                <th class="px-6 py-4 font-bold text-right pr-8">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-50">
                            <?php foreach($products as $p): 
                                $img = (!empty($p['p_image']) && file_exists("../uploads/products/".$p['p_image'])) ? "../uploads/products/".$p['p_image'] : "https://via.placeholder.com/150";
                            ?>
                            <tr class="hover:bg-pink-50/30 transition-colors group">
                                <td class="px-6 py-4 pl-8">
                                    <img src="<?= $img ?>" class="w-14 h-14 rounded-xl object-cover border border-gray-100 shadow-sm">
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 text-base"><?= htmlspecialchars($p['p_name']) ?></span>
                                        <span class="text-xs text-gray-400">SKU: <?= htmlspecialchars($p['p_sku']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-bold bg-pink-50 text-primary border border-pink-100">
                                        <?= htmlspecialchars($p['c_name'] ?? 'ไม่มีหมวดหมู่') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-bold text-gray-800">฿<?= number_format($p['p_price']) ?></td>
                                <td class="px-6 py-4 text-center">
                                    <?php if($p['p_stock'] == 0): ?>
                                        <span class="font-bold text-red-500 bg-red-50 px-2 py-1 rounded-md text-xs">หมด</span>
                                    <?php elseif($p['p_stock'] <= 10): ?>
                                        <span class="font-bold text-yellow-600"><?= $p['p_stock'] ?></span>
                                    <?php else: ?>
                                        <span class="font-bold text-green-500"><?= $p['p_stock'] ?></span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-6 py-4 text-center">
                                    <div class="relative inline-block w-10 align-middle select-none transition duration-200 ease-in">
                                        <input type="checkbox" onchange="window.location.href='?toggle_status=<?= $p['status'] ?>&id=<?= $p['p_id'] ?>'" <?= $p['status'] ? 'checked' : '' ?> class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-4 appearance-none cursor-pointer checked:right-0 checked:border-primary border-gray-300 left-0 transition-all duration-300"/>
                                        <label class="toggle-label block overflow-hidden h-5 rounded-full <?= $p['status'] ? 'bg-primary' : 'bg-gray-300' ?> cursor-pointer"></label>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 text-right pr-8">
                                    <button onclick="openEditModal(<?= $p['p_id'] ?>, '<?= htmlspecialchars($p['p_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['p_sku'], ENT_QUOTES) ?>', <?= $p['c_id'] ?>, <?= $p['p_price'] ?>, <?= $p['p_stock'] ?>, '<?= htmlspecialchars(str_replace(array("\r", "\n"), array('\r', '\n'), $p['p_detail'] ?? ''), ENT_QUOTES) ?>')" class="p-2 text-gray-400 hover:text-primary transition-colors"><span class="material-icons-round text-[20px]">edit</span></button>
                                    <button onclick="confirmDelete(<?= $p['p_id'] ?>)" class="p-2 text-gray-400 hover:text-red-500 transition-colors"><span class="material-icons-round text-[20px]">delete</span></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="addProductModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[100] hidden flex items-center justify-center opacity-0 transition-opacity duration-300 py-10">
    <div class="bg-white rounded-[2rem] w-full max-w-5xl h-full max-h-[90vh] shadow-2xl flex flex-col overflow-hidden modal-content transform scale-95 transition-transform duration-300">
        
        <div class="px-8 py-5 border-b border-pink-50 flex justify-between items-center bg-gray-50/50">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">เพิ่มสินค้าใหม่</h2>
            </div>
            <button type="button" onclick="closeModal('addProductModal')" class="w-8 h-8 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:text-red-500 hover:border-red-200 transition-colors shadow-sm">
                <span class="material-icons-round text-[20px]">close</span>
            </button>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" class="flex-1 overflow-hidden flex flex-col md:flex-row">
            <input type="hidden" name="action" value="add_product">
            
            <div class="w-full md:w-2/5 p-8 border-r border-gray-100 bg-pink-50/30 overflow-y-auto">
                <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2"><span class="material-icons-round text-primary">collections</span> รูปภาพสินค้า</h3>
                
                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-500 mb-2">รูปภาพหลัก (ปก)</label>
                    <div class="w-full aspect-square border-2 border-dashed border-pink-300 rounded-3xl bg-white flex flex-col items-center justify-center relative hover:border-primary transition-colors cursor-pointer overflow-hidden group">
                        <input type="file" name="main_image" id="mainImageInput" accept="image/*" required class="absolute inset-0 opacity-0 cursor-pointer z-10" onchange="previewMainImage(this, 'mainImagePreview', 'mainImagePlaceholder')">
                        <img id="mainImagePreview" src="" class="absolute inset-0 w-full h-full object-cover hidden z-0">
                        <div id="mainImagePlaceholder" class="text-center group-hover:scale-110 transition-transform">
                            <div class="w-16 h-16 bg-pink-100 text-primary rounded-full flex items-center justify-center mx-auto mb-2"><span class="material-icons-round text-3xl">add_photo_alternate</span></div>
                            <span class="text-sm font-bold text-primary">คลิกอัปโหลดรูปปก</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-bold text-gray-500">รูปภาพเพิ่มเติม (Gallery)</label>
                        <span class="text-[10px] text-primary bg-pink-50 px-2 py-0.5 rounded-full font-bold">อัปโหลดได้ไม่จำกัด</span>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-3" id="galleryPreviewContainer">
                        <label id="addGalleryBtn" class="aspect-square border-2 border-dashed border-pink-300 rounded-2xl bg-pink-50/50 flex flex-col items-center justify-center cursor-pointer hover:border-primary hover:text-primary transition-all text-primary/60 hover:bg-pink-50">
                            <span class="material-icons-round text-3xl">add_photo_alternate</span>
                            <span class="text-[10px] font-bold mt-1">เพิ่มรูป</span>
                            <input type="file" multiple accept="image/*" class="hidden" id="galleryInput">
                        </label>
                        <input type="file" name="gallery_images[]" multiple class="hidden" id="realGalleryInput">
                    </div>
                </div>
            </div>

            <div class="w-full md:w-3/5 p-8 overflow-y-auto color-scroll pb-20">
                <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2"><span class="material-icons-round text-primary">description</span> รายละเอียดทั่วไป</h3>
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">ชื่อสินค้า <span class="text-red-500">*</span></label>
                        <input type="text" name="p_name" required class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">รหัสสินค้า (SKU)</label>
                            <input type="text" name="p_sku" class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all font-mono text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">หมวดหมู่ <span class="text-red-500">*</span></label>
                            <select name="c_id" required class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all appearance-none">
                                <option value="">เลือก...</option>
                                <?php foreach($categories as $c): ?>
                                    <option value="<?= $c['c_id'] ?>"><?= $c['c_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">ราคา (บาท) <span class="text-red-500">*</span></label>
                            <input type="number" name="p_price" required min="0" class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all font-bold text-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">สต็อก <span class="text-red-500">*</span></label>
                            <input type="number" name="p_stock" required min="0" class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">รายละเอียด</label>
                        <textarea name="p_detail" rows="3" class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all resize-none"></textarea>
                    </div>

                    <div class="pt-4 border-t border-gray-100">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="font-bold text-gray-700 flex items-center gap-2"><span class="material-icons-round text-purple-500">palette</span> ตัวเลือกสี</h3>
                            <button type="button" onclick="addColorRow()" class="text-xs bg-purple-50 text-purple-600 font-bold px-3 py-1.5 rounded-full hover:bg-purple-100 flex items-center gap-1 transition-colors">
                                <span class="material-icons-round text-[14px]">add</span> เพิ่มสี
                            </button>
                        </div>
                        <div id="colorContainer" class="space-y-2"></div>
                    </div>
                </div>
            </div>
            
            <div class="absolute bottom-0 left-0 right-0 p-5 bg-white border-t border-gray-100 flex justify-end gap-3 rounded-b-[2rem] z-10">
                <button type="button" onclick="closeModal('addProductModal')" class="px-8 py-3 rounded-full bg-gray-100 text-gray-600 font-bold hover:bg-gray-200 transition-colors">ยกเลิก</button>
                <button type="submit" class="px-8 py-3 rounded-full bg-primary text-white font-bold hover:bg-pink-600 shadow-lg shadow-primary/30 transition-all transform hover:-translate-y-0.5">บันทึกสินค้า</button>
            </div>
        </form>
    </div>
</div>

<div id="editProductModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[100] hidden flex items-center justify-center opacity-0 transition-opacity duration-300 py-10">
    <div class="bg-white rounded-[2rem] w-full max-w-4xl h-auto max-h-[90vh] shadow-2xl flex flex-col overflow-hidden modal-content transform scale-95 transition-transform duration-300">
        
        <div class="px-8 py-5 border-b border-pink-50 flex justify-between items-center bg-gray-50/50">
            <h2 class="text-2xl font-bold text-gray-800">แก้ไขข้อมูลสินค้า</h2>
            <button type="button" onclick="closeModal('editProductModal')" class="w-8 h-8 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:text-red-500 shadow-sm">
                <span class="material-icons-round text-[20px]">close</span>
            </button>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" class="flex-1 overflow-y-auto p-8 color-scroll pb-24">
            <input type="hidden" name="action" value="edit_product">
            <input type="hidden" name="edit_p_id" id="edit_p_id">
            
            <div class="flex flex-col md:flex-row gap-8">
                <div class="w-full md:w-1/3">
                    <label class="block text-xs font-bold text-gray-500 mb-2">เปลี่ยนรูปปก (ไม่บังคับ)</label>
                    <div class="w-full aspect-square border-2 border-dashed border-gray-300 rounded-3xl bg-gray-50 flex flex-col items-center justify-center relative hover:border-primary transition-colors cursor-pointer overflow-hidden group">
                        <input type="file" name="main_image" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10" onchange="previewMainImage(this, 'editImagePreview', 'editImagePlaceholder')">
                        <img id="editImagePreview" src="" class="absolute inset-0 w-full h-full object-cover hidden z-0">
                        <div id="editImagePlaceholder" class="text-center group-hover:scale-110 transition-transform">
                            <div class="w-12 h-12 bg-white text-gray-400 rounded-full flex items-center justify-center mx-auto mb-2 shadow-sm"><span class="material-icons-round text-2xl">upload</span></div>
                            <span class="text-xs font-bold text-gray-500">อัปโหลดรูปใหม่</span>
                        </div>
                    </div>
                </div>

                <div class="w-full md:w-2/3 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1 ml-1">ชื่อสินค้า</label>
                        <input type="text" name="p_name" id="edit_p_name" required class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1 ml-1">รหัสสินค้า</label>
                            <input type="text" name="p_sku" id="edit_p_sku" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none font-mono text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1 ml-1">หมวดหมู่</label>
                            <select name="c_id" id="edit_c_id" required class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none">
                                <?php foreach($categories as $c): ?>
                                    <option value="<?= $c['c_id'] ?>"><?= $c['c_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1 ml-1">ราคา (บาท)</label>
                            <input type="number" name="p_price" id="edit_p_price" required min="0" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none text-primary font-bold">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1 ml-1">สต็อก</label>
                            <input type="number" name="p_stock" id="edit_p_stock" required min="0" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1 ml-1">รายละเอียด</label>
                        <textarea name="p_detail" id="edit_p_detail" rows="4" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none resize-none"></textarea>
                    </div>
                </div>
            </div>

            <div class="absolute bottom-0 left-0 right-0 p-5 bg-white border-t border-gray-100 flex justify-end gap-3 rounded-b-[2rem] z-10">
                <button type="button" onclick="closeModal('editProductModal')" class="px-8 py-3 rounded-full bg-gray-100 text-gray-600 font-bold hover:bg-gray-200 transition-colors">ยกเลิก</button>
                <button type="submit" class="px-8 py-3 rounded-full bg-primary text-white font-bold hover:bg-pink-600 shadow-lg shadow-primary/30 transition-all">บันทึกการเปลี่ยนแปลง</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) {
        const m = document.getElementById(id);
        m.classList.remove('hidden'); setTimeout(() => { m.classList.remove('opacity-0'); m.querySelector('.modal-content').classList.remove('scale-95'); }, 10);
    }
    function closeModal(id) {
        const m = document.getElementById(id);
        m.classList.add('opacity-0'); m.querySelector('.modal-content').classList.add('scale-95');
        setTimeout(() => m.classList.add('hidden'), 300);
    }

    // 🟢 โยนข้อมูลเก่าเข้าช่องกรอกสำหรับแก้ไข
    function openEditModal(id, name, sku, cid, price, stock, detail) {
        document.getElementById('edit_p_id').value = id;
        document.getElementById('edit_p_name').value = name;
        document.getElementById('edit_p_sku').value = sku;
        document.getElementById('edit_c_id').value = cid;
        document.getElementById('edit_p_price').value = price;
        document.getElementById('edit_p_stock').value = stock;
        // จัดการขึ้นบรรทัดใหม่
        document.getElementById('edit_p_detail').value = detail.replace(/\\n/g, "\n").replace(/\\r/g, "\r");
        openModal('editProductModal');
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'ลบสินค้านี้?', text: "ข้อมูลสินค้า รูปภาพ และสี จะถูกลบทิ้งทั้งหมด!", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#F43F85', cancelButtonColor: '#9CA3AF',
            confirmButtonText: 'ลบเลย!', cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-full px-6', cancelButton: 'rounded-full px-6' }
        }).then((result) => {
            if (result.isConfirmed) window.location.href = '?delete=' + id;
        });
    }

    <?php if (isset($_SESSION['success_msg'])): ?>
        Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '<?= $_SESSION['success_msg'] ?>', confirmButtonColor: '#F43F85', customClass: { popup: 'rounded-3xl' }});
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    // Preview รูปหลัก (ใช้ได้ทั้ง Modal เพิ่มและแก้ไข)
    function previewMainImage(input, previewId, placeholderId) {
        if (input.files && input.files[0]) {
            let reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(previewId).src = e.target.result;
                document.getElementById(previewId).classList.remove('hidden');
                document.getElementById(placeholderId).classList.add('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // จัดการ Gallery รูปภาพไม่อั้น (เหมือนเดิมเป๊ะ)
    let galleryFilesArray = []; 
    const galleryInput = document.getElementById('galleryInput');
    const realGalleryInput = document.getElementById('realGalleryInput');
    const container = document.getElementById('galleryPreviewContainer');
    const addBtn = document.getElementById('addGalleryBtn');

    if (galleryInput) {
        galleryInput.addEventListener('change', function() {
            const files = this.files;
            if (files.length > 0) {
                Array.from(files).forEach(file => { galleryFilesArray.push(file); });
                updateGalleryUI();
                updateRealInput();
            }
            this.value = ''; 
        });
    }

    function updateGalleryUI() {
        document.querySelectorAll('.gallery-preview-item').forEach(el => el.remove());
        galleryFilesArray.forEach((file, index) => {
            const objectUrl = URL.createObjectURL(file);
            let div = document.createElement('div');
            div.className = 'gallery-preview-item aspect-square rounded-2xl overflow-hidden border border-gray-200 shadow-sm relative group';
            div.innerHTML = `
                <img src="${objectUrl}" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                    <button type="button" onclick="removeGalleryImage(${index})" class="w-8 h-8 bg-white text-red-500 rounded-full flex items-center justify-center hover:bg-red-500 hover:text-white transition-colors transform hover:scale-110 shadow-md">
                        <span class="material-icons-round text-[18px]">delete</span>
                    </button>
                </div>
            `;
            container.insertBefore(div, addBtn);
        });
    }

    function removeGalleryImage(index) {
        galleryFilesArray.splice(index, 1);
        updateGalleryUI();
        updateRealInput();
    }

    function updateRealInput() {
        const dataTransfer = new DataTransfer();
        galleryFilesArray.forEach(file => { dataTransfer.items.add(file); });
        realGalleryInput.files = dataTransfer.files;
    }

    let colorIndex = 1;
    function addColorRow() {
        const colorContainer = document.getElementById('colorContainer');
        const row = document.createElement('div');
        row.className = 'flex items-center gap-3 p-2 bg-white border border-gray-200 rounded-xl shadow-sm animate-fade-in-up';
        row.innerHTML = `
            <input type="color" name="color_hexes[]" value="#F43F85" class="w-10 h-10 rounded-lg cursor-pointer border-0 p-0 bg-transparent flex-shrink-0">
            <input type="text" name="color_names[]" placeholder="ชื่อสี เช่น #01 W -'Bout to slay" class="flex-1 bg-transparent border-none text-sm focus:ring-0 outline-none p-0 text-gray-700">
            <button type="button" onclick="this.parentElement.remove()" class="w-8 h-8 rounded-full bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-500 flex items-center justify-center transition-colors flex-shrink-0">
                <span class="material-icons-round text-[18px]">close</span>
            </button>
        `;
        colorContainer.appendChild(row);
        colorIndex++;
    }
    document.addEventListener("DOMContentLoaded", addColorRow);
</script>
</body>
</html>