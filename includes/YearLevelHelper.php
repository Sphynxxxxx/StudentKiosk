<?php
class YearLevelHelper {
    public static function getYearLevelOptions($programId) {
        global $pdo;
        
        try {
            // Get distinct year levels for the program from sections table
            $stmt = $pdo->prepare("
                SELECT DISTINCT year_level 
                FROM sections 
                WHERE program_id = ? AND status = 'active'
                ORDER BY 
                    CASE year_level
                        WHEN '1st' THEN 1
                        WHEN '2nd' THEN 2
                        WHEN '3rd' THEN 3
                        WHEN '4th' THEN 4
                        WHEN '5th' THEN 5
                        ELSE 6
                    END
            ");
            $stmt->execute([$programId]);
            $yearLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $options = [];
            foreach ($yearLevels as $level) {
                $options[] = [
                    'value' => $level['year_level'],
                    'label' => $level['year_level'] . ' Year'
                ];
            }
            
            return $options;
            
        } catch (PDOException $e) {
            throw new Exception("Database error: " . $e->getMessage());
        }
    }
}
?>