<?php


namespace Models;


class StatusMaker
{
    protected static string $table_name = "user_statuses";

    public static function insertOrUpdate(string $chat_id, string $status): bool
    {
        $db = DbConnector::getConnection();
        $existStatus = self::getStatus($chat_id);
        if(is_null($existStatus)){
            $stmt = $db->prepare("INSERT INTO " . self::$table_name . " (chat_id, status) VALUES (?, ?)");
            $stmt->bindParam(1, $chat_id);
            $stmt->bindParam(2, $status);
            return $stmt->execute();
        } else {
            $stmt = $db->prepare("UPDATE " . self::$table_name . " SET status=?");
            $stmt->bindParam(1, $status);
            return $stmt->execute();
        }
    }

    public static function getStatus(string $chat_id): ?string
    {
        $db = DbConnector::getConnection();
        $sql= "SELECT * FROM " . self::$table_name . " WHERE chat_id='" .  $chat_id . "' LIMIT 1";
        $result = $db->query($sql);
        $result = $result->fetchAll();
        if(empty($result)) return null;
        return $result[0]['status'];
    }
}