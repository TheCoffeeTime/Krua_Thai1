<?php
/**
 * Somdul Table - Referral Manager
 * File: ReferralManager.php
 * Handles all referral system operations
 */

class ReferralManager {
    
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Generate a unique UUID
     */
    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Check if user was referred and process referral reward
     * Call this when a user successfully completes their first subscription
     */
    public function processReferralReward($userId, $subscriptionId) {
        try {
            $this->db->beginTransaction();
            
            // Get user email to find referral
            $stmt = $this->db->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->db->rollBack();
                return false;
            }
            
            // Find pending referral for this email
            $stmt = $this->db->prepare("
                SELECT r.*, u.name as referrer_name, u.email as referrer_email
                FROM referrals r
                JOIN users u ON r.referrer_id = u.id
                WHERE r.referred_email = ? 
                AND r.status = 'pending'
                AND r.expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$user['email']]);
            $referral = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$referral) {
                $this->db->rollBack();
                return false; // No referral found
            }
            
            // Update referral status
            $stmt = $this->db->prepare("
                UPDATE referrals 
                SET status = 'completed', 
                    referred_user_id = ?,
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$userId, $referral['id']]);
            
            // Get current referrer credits
            $stmt = $this->db->prepare("
                SELECT referral_credits, total_referrals, total_referral_earnings 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$referral['referrer_id']]);
            $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $current_credits = $referrer['referral_credits'] ?? 0;
            $reward_amount = $referral['reward_amount'] ?? 10.00;
            $new_credits = $current_credits + $reward_amount;
            $new_total_referrals = ($referrer['total_referrals'] ?? 0) + 1;
            $new_total_earnings = ($referrer['total_referral_earnings'] ?? 0) + $reward_amount;
            
            // Update referrer's credits and stats
            $stmt = $this->db->prepare("
                UPDATE users 
                SET referral_credits = ?,
                    total_referrals = ?,
                    total_referral_earnings = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $new_credits,
                $new_total_referrals,
                $new_total_earnings,
                $referral['referrer_id']
            ]);
            
            // Create referral transaction record
            $transaction_id = $this->generateUUID();
            $stmt = $this->db->prepare("
                INSERT INTO referral_transactions 
                (id, user_id, referral_id, transaction_type, amount, description, balance_before, balance_after, created_at)
                VALUES (?, ?, ?, 'earned', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $transaction_id,
                $referral['referrer_id'],
                $referral['id'],
                $reward_amount,
                "Referral reward for {$user['email']}",
                $current_credits,
                $new_credits
            ]);
            
            // Create notification for referrer (if NotificationManager exists)
            if (class_exists('NotificationManager')) {
                try {
                    $notificationManager = new NotificationManager($this->db);
                    $notificationManager->createNotification(
                        $referral['referrer_id'],
                        'referral_reward',
                        'Referral Reward Earned!',
                        "Your friend {$user['email']} just subscribed! You've earned $" . number_format($reward_amount, 2) . " in referral credits.",
                        [
                            'reward_amount' => $reward_amount,
                            'referred_email' => $user['email'],
                            'new_balance' => $new_credits
                        ]
                    );
                } catch (Exception $e) {
                    error_log("Failed to create referral notification: " . $e->getMessage());
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'referral_id' => $referral['id'],
                'referrer_id' => $referral['referrer_id'],
                'referrer_name' => $referral['referrer_name'],
                'reward_amount' => $reward_amount,
                'new_credits' => $new_credits
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Referral processing error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get referral by code (for registration page)
     */
    public function getReferralByCode($referralCode) {
        try {
            $stmt = $this->db->prepare("
                SELECT r.*, u.name as referrer_name
                FROM referrals r
                JOIN users u ON r.referrer_id = u.id
                WHERE r.referral_code = ?
                AND r.status = 'pending'
                AND r.expires_at > NOW()
            ");
            $stmt->execute([$referralCode]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get referral by code error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's referral statistics
     */
    public function getUserReferralStats($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_sent,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_referrals,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_referrals,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_referrals,
                    SUM(CASE WHEN status = 'completed' THEN reward_amount ELSE 0 END) as total_earned
                FROM referrals 
                WHERE referrer_id = ?
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get referral stats error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get user's recent referrals
     */
    public function getUserRecentReferrals($userId, $limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT r.*, u.name as referred_user_name
                FROM referrals r
                LEFT JOIN users u ON r.referred_user_id = u.id
                WHERE r.referrer_id = ?
                ORDER BY r.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get recent referrals error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create a new referral
     */
    public function createReferral($referrerId, $friendEmail, $rewardAmount = 10.00) {
        try {
            // Check if referrer exists
            $stmt = $this->db->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$referrerId]);
            $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$referrer) {
                return ['success' => false, 'message' => 'Invalid referrer.'];
            }
            
            // Check if trying to refer themselves
            if (strtolower($referrer['email']) === strtolower($friendEmail)) {
                return ['success' => false, 'message' => 'You cannot refer yourself!'];
            }
            
            // Check if already referred
            $stmt = $this->db->prepare("SELECT id FROM referrals WHERE referrer_id = ? AND referred_email = ?");
            $stmt->execute([$referrerId, $friendEmail]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'You have already referred this email address.'];
            }
            
            // Check if email is already a user
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$friendEmail]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'This person is already a Somdul Table member!'];
            }
            
            // Generate unique referral code
            $referralCode = $this->generateReferralCode();
            
            // Create referral
            $referralId = $this->generateUUID();
            $stmt = $this->db->prepare("
                INSERT INTO referrals 
                (id, referrer_id, referred_email, referral_code, status, reward_amount, created_at, expires_at)
                VALUES (?, ?, ?, ?, 'pending', ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))
            ");
            
            $result = $stmt->execute([
                $referralId,
                $referrerId,
                $friendEmail,
                $referralCode,
                $rewardAmount
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Referral sent successfully!',
                    'referral_id' => $referralId,
                    'referral_code' => $referralCode
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to create referral.'];
            }
            
        } catch (Exception $e) {
            error_log("Create referral error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while creating the referral.'];
        }
    }
    
    /**
     * Generate a unique referral code
     */
    private function generateReferralCode($length = 8) {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $maxAttempts = 10;
        
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
            
            // Check if code exists
            $stmt = $this->db->prepare("SELECT id FROM referrals WHERE referral_code = ?");
            $stmt->execute([$code]);
            
            if (!$stmt->fetch()) {
                return $code;
            }
        }
        
        // Fallback
        return substr(str_shuffle($characters), 0, 6) . date('s');
    }
    
    /**
     * Expire old pending referrals (run this periodically)
     */
    public function expireOldReferrals() {
        try {
            $stmt = $this->db->prepare("
                UPDATE referrals 
                SET status = 'expired', updated_at = NOW()
                WHERE status = 'pending' 
                AND expires_at < NOW()
            ");
            $result = $stmt->execute();
            $expiredCount = $stmt->rowCount();
            
            error_log("Expired $expiredCount old referrals");
            return $expiredCount;
            
        } catch (Exception $e) {
            error_log("Expire referrals error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Use referral credits for payment (future feature)
     */
    public function useCredits($userId, $amount, $description = 'Credit usage') {
        try {
            $this->db->beginTransaction();
            
            // Get current credits
            $stmt = $this->db->prepare("SELECT referral_credits FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || $user['referral_credits'] < $amount) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Insufficient credits.'];
            }
            
            $newCredits = $user['referral_credits'] - $amount;
            
            // Update user credits
            $stmt = $this->db->prepare("
                UPDATE users 
                SET referral_credits = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$newCredits, $userId]);
            
            // Create transaction record
            $transactionId = $this->generateUUID();
            $stmt = $this->db->prepare("
                INSERT INTO referral_transactions 
                (id, user_id, referral_id, transaction_type, amount, description, balance_before, balance_after, created_at)
                VALUES (?, ?, NULL, 'used', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $transactionId,
                $userId,
                $amount,
                $description,
                $user['referral_credits'],
                $newCredits
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'new_balance' => $newCredits,
                'amount_used' => $amount
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Use credits error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred.'];
        }
    }
}
?>