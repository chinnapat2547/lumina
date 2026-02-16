<?php
// เริ่มต้น Session เพื่อให้รู้จักตัวแปร Session ที่กำลังทำงานอยู่
session_start();

// ล้างค่าตัวแปร Session ทั้งหมดที่เก็บไว้ (เช่น u_id, u_username, u_name)
$_SESSION = array();

// ลบ Cookie ที่เกี่ยวข้องกับ Session (เพื่อความปลอดภัยสูงสุด)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ทำลาย Session ทิ้ง
session_destroy();

header("Location: ../home.php");
exit();
?>