<?php

namespace App\Service;

use PDO;
use Exception;

/**
 * FontService
 * Handles custom font management, uploads, and detection
 */
class FontService
{
    private ?PDO $pdo = null;
    private string $uploadDir;
    private string $fontsJsonFile;
    private array $allowedFormats = ['woff', 'woff2', 'ttf', 'otf'];
    private int $maxFileSize = 5242880; // 5MB
    private bool $useDatabase = false;

    public function __construct(PDO $pdo = null)
    {
        $this->uploadDir = __DIR__ . '/../../public/assets/fonts/custom/';
        $this->fontsJsonFile = $this->uploadDir . 'fonts.json';
        
        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        // Try to establish database connection
        try {
            $this->pdo = $pdo ?? $this->getDatabaseConnection();
            $this->useDatabase = ($this->pdo !== null);
        } catch (Exception $e) {
            // Database not available, use JSON file fallback
            $this->useDatabase = false;
            $this->pdo = null;
        }
    }

    /**
     * Get database connection (optional)
     */
    private function getDatabaseConnection(): ?PDO
    {
        try {
            $configFile = __DIR__ . '/../../config/database.php';
            
            if (!file_exists($configFile)) {
                return null;
            }
            
            $config = require $configFile;
            
            // Check if config has required keys
            if (!isset($config['host']) || !isset($config['database']) || 
                !isset($config['username']) || !isset($config['password'])) {
                return null;
            }
            
            $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
            
            return new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (Exception $e) {
            // Return null if connection fails
            return null;
        }
    }

    /**
     * Check if database is available
     */
    public function isDatabaseAvailable(): bool
    {
        return $this->useDatabase && $this->pdo !== null;
    }

    /**
     * Upload and register a custom font
     * 
     * @param array $file The uploaded file from $_FILES
     * @param string $fontName Display name for the font
     * @param string $fontFamily Font-family CSS value
     * @param string $category Font category
     * @param int $userId User ID who uploaded
     * @return array Result with success status and message
     */
    public function uploadFont(array $file, string $fontName, string $fontFamily, string $category = 'sans-serif', int $userId = null): array
    {
        try {
            // Validate file
            $validation = $this->validateFontFile($file);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['error']];
            }

            // Check if font family already exists
            if ($this->fontFamilyExists($fontFamily)) {
                return ['success' => false, 'message' => 'Font family already exists'];
            }

            // Get file info
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $sanitizedFamily = $this->sanitizeFileName($fontFamily);
            $fileName = $sanitizedFamily . '_' . time() . '.' . $fileExtension;
            $filePath = $this->uploadDir . $fileName;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return ['success' => false, 'message' => 'Failed to upload font file'];
            }

            // Register font (database or JSON)
            $relativePath = 'assets/fonts/custom/' . $fileName;
            $fontData = [
                'font_name' => $fontName,
                'font_family' => $fontFamily,
                'font_category' => $category,
                'file_path' => $relativePath,
                'file_format' => $fileExtension,
                'file_size' => filesize($filePath),
                'uploaded_by' => $userId
            ];
            
            if ($this->isDatabaseAvailable()) {
                $fontId = $this->registerFontInDatabase($fontData);
            } else {
                $fontId = $this->registerFontInJson($fontData);
            }

            // Generate CSS for the font
            $this->generateFontFaceCSS();

            return [
                'success' => true,
                'message' => 'Font uploaded successfully',
                'font_id' => $fontId,
                'file_path' => $relativePath
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Validate uploaded font file
     */
    private function validateFontFile(array $file): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['valid' => false, 'error' => 'Invalid file upload'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Upload error occurred'];
        }

        if ($file['size'] > $this->maxFileSize) {
            return ['valid' => false, 'error' => 'File size exceeds 5MB limit'];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedFormats)) {
            return ['valid' => false, 'error' => 'Invalid file format. Allowed: ' . implode(', ', $this->allowedFormats)];
        }

        // Check MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $allowedMimes = [
            'font/woff', 'font/woff2', 'application/font-woff', 'application/font-woff2',
            'font/ttf', 'font/otf', 'application/x-font-ttf', 'application/x-font-otf',
            'application/octet-stream' // Fallback for some systems
        ];
        
        if (!in_array($mimeType, $allowedMimes)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }

        return ['valid' => true];
    }

    /**
     * Register font in database
     */
    private function registerFontInDatabase(array $fontData): int
    {
        $sql = "INSERT INTO custom_fonts 
                (font_name, font_family, font_category, file_path, file_format, file_size, uploaded_by)
                VALUES (:font_name, :font_family, :font_category, :file_path, :file_format, :file_size, :uploaded_by)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($fontData);
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Register font in JSON file
     */
    private function registerFontInJson(array $fontData): int
    {
        $fonts = $this->readFontsJson();
        
        // Generate auto-increment ID
        $maxId = 0;
        foreach ($fonts as $font) {
            if (isset($font['id']) && $font['id'] > $maxId) {
                $maxId = $font['id'];
            }
        }
        $fontData['id'] = $maxId + 1;
        $fontData['is_active'] = 1;
        $fontData['created_at'] = date('Y-m-d H:i:s');
        $fontData['updated_at'] = date('Y-m-d H:i:s');
        
        $fonts[] = $fontData;
        $this->writeFontsJson($fonts);
        
        return $fontData['id'];
    }

    /**
     * Read fonts from JSON file
     */
    private function readFontsJson(): array
    {
        if (!file_exists($this->fontsJsonFile)) {
            return [];
        }
        
        $contents = file_get_contents($this->fontsJsonFile);
        $fonts = json_decode($contents, true);
        
        return is_array($fonts) ? $fonts : [];
    }

    /**
     * Write fonts to JSON file
     */
    private function writeFontsJson(array $fonts): void
    {
        $json = json_encode($fonts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->fontsJsonFile, $json);
    }

    /**
     * Check if font family exists
     */
    private function fontFamilyExists(string $fontFamily): bool
    {
        if ($this->isDatabaseAvailable()) {
            $sql = "SELECT COUNT(*) FROM custom_fonts WHERE font_family = :font_family AND is_active = 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['font_family' => $fontFamily]);
            return $stmt->fetchColumn() > 0;
        } else {
            $fonts = $this->readFontsJson();
            foreach ($fonts as $font) {
                if ($font['font_family'] === $fontFamily && ($font['is_active'] ?? 1) == 1) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Get all custom fonts
     */
    public function getAllCustomFonts(bool $activeOnly = true): array
    {
        if ($this->isDatabaseAvailable()) {
            $sql = "SELECT * FROM custom_fonts";
            if ($activeOnly) {
                $sql .= " WHERE is_active = 1";
            }
            $sql .= " ORDER BY font_name ASC";
            
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll();
        } else {
            $fonts = $this->readFontsJson();
            if ($activeOnly) {
                $fonts = array_filter($fonts, function($font) {
                    return ($font['is_active'] ?? 1) == 1;
                });
            }
            usort($fonts, function($a, $b) {
                return strcmp($a['font_name'], $b['font_name']);
            });
            return $fonts;
        }
    }

    /**
     * Get custom fonts by category
     */
    public function getFontsByCategory(string $category): array
    {
        if ($this->isDatabaseAvailable()) {
            $sql = "SELECT * FROM custom_fonts WHERE font_category = :category AND is_active = 1 ORDER BY font_name ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['category' => $category]);
            return $stmt->fetchAll();
        } else {
            $fonts = $this->readFontsJson();
            $filtered = array_filter($fonts, function($font) use ($category) {
                return $font['font_category'] === $category && ($font['is_active'] ?? 1) == 1;
            });
            usort($filtered, function($a, $b) {
                return strcmp($a['font_name'], $b['font_name']);
            });
            return array_values($filtered);
        }
    }

    /**
     * Delete custom font
     */
    public function deleteFont(int $fontId): bool
    {
        try {
            // Get font info
            $font = null;
            
            if ($this->isDatabaseAvailable()) {
                $sql = "SELECT file_path FROM custom_fonts WHERE id = :id";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['id' => $fontId]);
                $font = $stmt->fetch();
            } else {
                $fonts = $this->readFontsJson();
                foreach ($fonts as $f) {
                    if ($f['id'] == $fontId) {
                        $font = $f;
                        break;
                    }
                }
            }
            
            if (!$font) {
                return false;
            }

            // Delete file
            $fullPath = __DIR__ . '/../../public/' . $font['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            // Delete from storage
            if ($this->isDatabaseAvailable()) {
                $sql = "DELETE FROM custom_fonts WHERE id = :id";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['id' => $fontId]);
            } else {
                $fonts = $this->readFontsJson();
                $fonts = array_filter($fonts, function($f) use ($fontId) {
                    return $f['id'] != $fontId;
                });
                $this->writeFontsJson(array_values($fonts));
            }

            // Regenerate CSS
            $this->generateFontFaceCSS();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Generate @font-face CSS for all custom fonts
     */
    public function generateFontFaceCSS(): void
    {
        $fonts = $this->getAllCustomFonts();
        $css = "/**\n * Custom Fonts - Auto-generated\n * Do not edit manually\n */\n\n";

        foreach ($fonts as $font) {
            $css .= "@font-face {\n";
            $css .= "  font-family: '{$font['font_family']}';\n";
            $css .= "  src: url('../fonts/custom/" . basename($font['file_path']) . "')";
            
            // Add format hint
            $format = $font['file_format'];
            if ($format === 'ttf') $format = 'truetype';
            if ($format === 'otf') $format = 'opentype';
            $css .= " format('{$format}');\n";
            
            $css .= "  font-weight: {$font['font_weight']};\n";
            $css .= "  font-style: {$font['font_style']};\n";
            $css .= "  font-display: swap;\n";
            $css .= "}\n\n";

            // Add CSS custom property
            $varName = '--font-family-' . $this->sanitizeFileName($font['font_family']);
            $css .= ":root {\n";
            $css .= "  {$varName}: '{$font['font_family']}', sans-serif;\n";
            $css .= "}\n\n";

            // Add body selector
            $dataAttr = $this->sanitizeFileName($font['font_family']);
            $css .= "body[data-font=\"{$dataAttr}\"] {\n";
            $css .= "  font-family: var({$varName});\n";
            $css .= "}\n\n";
        }

        // Write to CSS file
        $cssFile = __DIR__ . '/../../public/assets/css/custom-fonts.css';
        file_put_contents($cssFile, $css);
    }

    /**
     * Sanitize filename
     */
    public function sanitizeFileName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9_-]/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);
        return trim($name, '-');
    }

    /**
     * Toggle font active status
     */
    public function toggleFontStatus(int $fontId, bool $active): bool
    {
        try {
            if ($this->isDatabaseAvailable()) {
                $sql = "UPDATE custom_fonts SET is_active = :active WHERE id = :id";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['active' => $active ? 1 : 0, 'id' => $fontId]);
            } else {
                $fonts = $this->readFontsJson();
                foreach ($fonts as &$font) {
                    if ($font['id'] == $fontId) {
                        $font['is_active'] = $active ? 1 : 0;
                        $font['updated_at'] = date('Y-m-d H:i:s');
                        break;
                    }
                }
                $this->writeFontsJson($fonts);
            }
            
            $this->generateFontFaceCSS();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
