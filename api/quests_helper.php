<?php
require_once __DIR__ . '/../db.php';

function recordQuestProgress($userId, $questType) {
    $db = getDbConnection();

    // Check if the quest is active
    $stmt = $db->prepare("SELECT id, reward_pts, period FROM quests WHERE type = ? AND is_active = TRUE");
    $stmt->execute([$questType]);
    $quest = $stmt->fetch();

    if (!$quest) {
        return false; // Quest not active or doesn't exist
    }

    // Check if already completed in the current period
    $periodCond = $quest['period'] === 'weekly'
        ? "YEARWEEK(completed_at, 1) = YEARWEEK(CURRENT_DATE(), 1)"
        : "DATE(completed_at) = CURRENT_DATE()";

    $checkStmt = $db->prepare("SELECT 1 FROM user_quests WHERE user_id = ? AND quest_id = ? AND " . $periodCond);
    $checkStmt->execute([$userId, $quest['id']]);

    if ($checkStmt->fetch()) {
        return false; // Already completed for the period
    }

    // Record progress
    $insertStmt = $db->prepare("INSERT INTO user_quests (user_id, quest_id) VALUES (?, ?)");
    $insertStmt->execute([$userId, $quest['id']]);

    // Award points
    $updateStmt = $db->prepare("UPDATE users SET pts = pts + ? WHERE id = ?");
    $updateStmt->execute([$quest['reward_pts'], $userId]);

    return true;
}
