<?php
class FileUpload {
    private $uploadDir = '../uploads/';
    private $allowedTypes = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    private $maxFileSize = 10 * 1024 * 1024; // 10MB
    
    public function __construct() {
        // Create upload directories if they don't exist
        $this->createDirectories();
    }
    
    private function createDirectories() {
        $dirs = [
            $this->uploadDir,
            $this->uploadDir . 'assignments/',
            $this->uploadDir . 'submissions/',
            $this->uploadDir . 'schools/',
            $this->uploadDir . 'temp/'
        ];
        
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    public function uploadFile($file, $type = 'assignments', $customName = null) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('No file uploaded or invalid file');
        }
        
        // Validate file size
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('File size exceeds maximum limit of 10MB');
        }
        
        // Validate file type
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!array_key_exists($fileExtension, $this->allowedTypes)) {
            throw new Exception('File type not allowed. Allowed types: ' . implode(', ', array_keys($this->allowedTypes)));
        }
        
        // Verify MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if ($mimeType !== $this->allowedTypes[$fileExtension]) {
            throw new Exception('File MIME type does not match extension');
        }
        
        // Generate unique filename
        $fileName = $customName ? $customName : uniqid() . '_' . time();
        $fileName .= '.' . $fileExtension;
        
        $uploadPath = $this->uploadDir . $type . '/' . $fileName;
        
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        return [
            'filename' => $fileName,
            'original_name' => $file['name'],
            'path' => $uploadPath,
            'relative_path' => $type . '/' . $fileName,
            'size' => $file['size'],
            'type' => $mimeType,
            'extension' => $fileExtension
        ];
    }
    
    public function deleteFile($filePath) {
        $fullPath = $this->uploadDir . $filePath;
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }
    
    public function getFileInfo($filePath) {
        $fullPath = $this->uploadDir . $filePath;
        if (!file_exists($fullPath)) {
            return false;
        }
        
        return [
            'exists' => true,
            'size' => filesize($fullPath),
            'modified' => filemtime($fullPath),
            'path' => $fullPath
        ];
    }
    
    public function serveFile($filePath, $originalName = null) {
        $fullPath = $this->uploadDir . $filePath;
        
        if (!file_exists($fullPath)) {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
            exit;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fullPath);
        finfo_close($finfo);
        
        $fileName = $originalName ?: basename($fullPath);
        
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($fullPath));
        header('Content-Disposition: inline; filename="' . $fileName . '"');
        header('Cache-Control: public, max-age=3600');
        
        readfile($fullPath);
        exit;
    }
}
?>
