<?php
declare(strict_types=1);

function uploaded_icon_directory(): string { return dirname(__DIR__).'/config/uploaded-icons'; }
function uploaded_icon_filename(array $settings, int $id): ?string {
    $filename=$settings['system_upload_'.$id] ?? '';
    return preg_match('/^[a-f0-9]{32}\.png$/D', $filename) ? $filename : null;
}
function uploaded_icon_remove(?string $filename): void {
    if ($filename && preg_match('/^[a-f0-9]{32}\.png$/D',$filename)) @unlink(uploaded_icon_directory().'/'.$filename);
}
/** Decode and re-encode raster files; never retain the user's original filename or bytes. */
function uploaded_icon_save(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new InvalidArgumentException('อัปโหลดรูปไม่สำเร็จ กรุณาเลือกรูป PNG, JPG หรือ WebP ขนาดไม่เกิน 2 MB แล้วลองใหม่');
    $path=$file['tmp_name'] ?? '';
    if (!is_string($path) || !is_uploaded_file($path)) throw new InvalidArgumentException('ไฟล์อัปโหลดไม่ถูกต้อง');
    $size=filesize($path);
    if (!$size || $size>2*1024*1024) throw new InvalidArgumentException('รูปไอคอนต้องมีขนาดไม่เกิน 2 MB');
    $info=@getimagesize($path);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!$info || !in_array($mime,['image/png','image/jpeg','image/webp'],true) || ($info['mime'] ?? '')!==$mime) throw new InvalidArgumentException('รองรับเฉพาะรูป PNG, JPG และ WebP ไม่รองรับ SVG หรือไฟล์ชนิดอื่น');
    if ($info[0]>4096 || $info[1]>4096 || $info[0]*$info[1]>8388608) throw new InvalidArgumentException('รูปใหญ่เกินไป กรุณาใช้รูปไม่เกิน 4096 × 4096 พิกเซล และไม่เกิน 8 ล้านพิกเซล');
    if (!function_exists('imagecreatefromstring')) throw new InvalidArgumentException('โฮสติ้งต้องเปิดส่วนขยาย PHP GD เพื่ออัปโหลดรูปไอคอน');
    $image=@imagecreatefromstring(file_get_contents($path));
    if (!$image) throw new InvalidArgumentException('อ่านรูปไม่ได้ กรุณาเลือกรูปใหม่');
    $scale=min(1,256/max($info[0],$info[1]));
    $thumbnail=imagecreatetruecolor(max(1,(int)round($info[0]*$scale)),max(1,(int)round($info[1]*$scale)));
    imagealphablending($thumbnail,false); imagesavealpha($thumbnail,true);
    $directory=uploaded_icon_directory(); $temporary=null;
    try {
        if (!imagecopyresampled($thumbnail,$image,0,0,0,0,imagesx($thumbnail),imagesy($thumbnail),$info[0],$info[1])) throw new InvalidArgumentException('ย่อรูปไม่สำเร็จ กรุณาเลือกรูปใหม่');
        if (!is_dir($directory) && !@mkdir($directory,0700,true) && !is_dir($directory)) throw new InvalidArgumentException('บันทึกรูปไม่ได้ กรุณาให้ PHP เขียนโฟลเดอร์ config/uploaded-icons ได้');
        $temporary=@tempnam($directory,'icon-');
        if (!$temporary || !@imagepng($thumbnail,$temporary,6)) throw new InvalidArgumentException('บันทึกรูปไม่ได้ กรุณาตรวจสิทธิ์โฟลเดอร์ config/uploaded-icons');
        @chmod($temporary,0600);
        $filename=bin2hex(random_bytes(16)).'.png';
        if (!@rename($temporary,$directory.'/'.$filename)) throw new InvalidArgumentException('บันทึกรูปไม่ได้ กรุณาตรวจสิทธิ์โฟลเดอร์ config/uploaded-icons');
        return $filename;
    } finally {
        if ($temporary && is_file($temporary)) @unlink($temporary);
        imagedestroy($thumbnail); imagedestroy($image);
    }
}
