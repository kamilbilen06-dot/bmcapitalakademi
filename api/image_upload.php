<?php
/**
 * Profil fotoğrafı kaydı — mümkünse GD ile yüksek kalite JPEG.
 */
function save_instructor_photo(array $file) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Dosya yüklenemedi', 'code' => 400];
    }

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        return ['ok' => false, 'error' => 'jpg/png/webp kullanın', 'code' => 422];
    }

    $dirRel = 'uploads/instructors';
    $dirAbs = __DIR__ . '/../' . $dirRel;
    if (!is_dir($dirAbs)) mkdir($dirAbs, 0755, true);

    $tmp = $file['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info) {
        return ['ok' => false, 'error' => 'Geçersiz görsel', 'code' => 422];
    }

    $name = 'ins_' . time() . '_' . bin2hex(random_bytes(3)) . '.jpg';
    $destAbs = $dirAbs . '/' . $name;
    $destRel = $dirRel . '/' . $name;

    // GD varsa yüksek kalite JPEG olarak kaydet (en fazla 1600px kenar)
    if (function_exists('imagecreatetruecolor') && function_exists('imagejpeg')) {
        $src = null;
        switch ($info[2]) {
            case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($tmp); break;
            case IMAGETYPE_PNG:  $src = @imagecreatefrompng($tmp); break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($tmp);
                break;
            case IMAGETYPE_GIF:  $src = @imagecreatefromgif($tmp); break;
        }
        if ($src) {
            $w = imagesx($src);
            $h = imagesy($src);
            $max = 1600;
            $nw = $w;
            $nh = $h;
            if ($w > $max || $h > $max) {
                if ($w >= $h) {
                    $nw = $max;
                    $nh = (int)round($h * ($max / $w));
                } else {
                    $nh = $max;
                    $nw = (int)round($w * ($max / $h));
                }
            }
            $dst = imagecreatetruecolor($nw, $nh);
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefill($dst, 0, 0, $white);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagejpeg($dst, $destAbs, 92);
            imagedestroy($dst);
            imagedestroy($src);
            return ['ok' => true, 'path' => $destRel];
        }
    }

    // GD yoksa olduğu gibi taşı (kırpıcı zaten jpg gönderir)
    if ($ext !== 'jpg' && $ext !== 'jpeg') {
        $name = 'ins_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $destAbs = $dirAbs . '/' . $name;
        $destRel = $dirRel . '/' . $name;
    }
    if (!move_uploaded_file($tmp, $destAbs)) {
        return ['ok' => false, 'error' => 'Dosya kaydedilemedi', 'code' => 500];
    }
    return ['ok' => true, 'path' => $destRel];
}
