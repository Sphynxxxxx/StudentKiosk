<?php
require_once __DIR__ . '/../config/database.php';

class YearLevelHelper {
    private static function getDatabaseConnection() {
        global $pdo;
        return $pdo;
    }

    public static function getProgramDuration($programId) {
        try {
            $pdo = self::getDatabaseConnection();
            $stmt = $pdo->prepare("SELECT duration_years FROM programs WHERE id = ?");
            $stmt->execute([$programId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['duration_years'] : 4; // Default to 4 if not found
        } catch (PDOException $e) {
            error_log("Error getting program duration: " . $e->getMessage());
            return 4; // Default to 4 years on error
        }
    }

    public static function getYearLevelsByProgram($programId = null) {
        $duration = $programId ? self::getProgramDuration($programId) : 4;
        $yearLevels = [];
        
        for ($i = 1; $i <= $duration; $i++) {
            switch ($i) {
                case 1:
                    $yearLevels[$i . 'st'] = $i . 'ST YEAR';
                    break;
                case 2:
                    $yearLevels[$i . 'nd'] = $i . 'ND YEAR';
                    break;
                case 3:
                    $yearLevels[$i . 'rd'] = $i . 'RD YEAR';
                    break;
                default:
                    $yearLevels[$i . 'th'] = $i . 'TH YEAR';
                    break;
            }
        }
        
        return $yearLevels;
    }

    public static function formatYearLevel($yearLevel) {
        if (empty($yearLevel)) return 'N/A';
        
        // Already formatted
        if (strpos($yearLevel, ' YEAR') !== false) {
            return $yearLevel;
        }
        
        // Convert numeric to formatted
        if (is_numeric($yearLevel)) {
            $yearLevels = self::getYearLevelsByProgram();
            return $yearLevels[$yearLevel] ?? 'N/A';
        }
        
        // Convert short form to full form
        $shortForms = ['1st', '2nd', '3rd', '4th', '5th'];
        $fullForms = ['1ST YEAR', '2ND YEAR', '3RD YEAR', '4TH YEAR', '5TH YEAR'];
        $index = array_search(strtolower($yearLevel), array_map('strtolower', $shortForms));
        
        return $index !== false ? $fullForms[$index] : strtoupper($yearLevel);
    }

    public static function getYearLevelOptions($programId = null) {
        $yearLevels = self::getYearLevelsByProgram($programId);
        $options = [];
        
        foreach ($yearLevels as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label
            ];
        }
        
        return $options;
    }
    
    public static function getYearLevelOptionsHtml($programId = null, $selectedValue = '') {
        $options = self::getYearLevelOptions($programId);
        $html = '<option value="">Select Year Level</option>';
        
        foreach ($options as $option) {
            $selected = ($selectedValue == $option['value']) ? 'selected' : '';
            $html .= sprintf(
                '<option value="%s" %s>%s</option>',
                htmlspecialchars($option['value']),
                $selected,
                htmlspecialchars($option['label'])
            );
        }
        
        return $html;
    }
}
?>