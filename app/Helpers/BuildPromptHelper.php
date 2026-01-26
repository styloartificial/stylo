<?php

namespace App\Helpers;

use App\Services\FirebaseService;
use App\Services\OpenAIService;
use App\Helpers\S3Helper;
use App\Helpers\FirebaseLogHelper;
use App\Models\Scan;

class BuildPromptHelper {
    public static function run(Scan $scan) {
        // Initial
		$db = FirebaseService::database();
		$ticketId = $scan->ticket_id;
		
		FirebaseLogHelper::logPromptBuild($db, $ticketId);
		
		$user = $scan->user;
		$userProfileImg = S3Helper::downloadToTemp($user->userDetail->img_url);
		$userDetail = $user->userDetail;
		$userGender = $userDetail->gender;
		$userHeight = $userDetail->height;
		$userWeight = $userDetail->weight;
		$userSkinTone = "{$userDetail->skinTone->title} ({$userDetail->skinTone->description})";
		
		$scanImg = S3Helper::downloadToTemp($scan->img_url);
		$scanCategoryName = $scan->scanCategory->title;
		
		$prompt = "Berdasarkan foto orang ini, buatkan summary outfit $scanCategoryName untuk $userGender, tinggi $userHeight cm, berat $userWeight kg, skin tone $userSkinTone. Sertakan rekomendasi merk, hasil dalam JSON {summary, products}. Dapatkan juga 3 foto orang ini dengan rekomendasi merk tersebut dengan 3 pose.";
		
		$promptBuild = [
			'prompt' => $prompt,
			'temp_images' => [$userProfileImg, $scanImg],
			'generate_images' => 3
		];
		// Done Initial
		
		try {
			FirebaseLogHelper::logPromptSent($db, $ticketId);
			$result = OpenAIService::run($promptBuild);
			FirebaseLogHelper::logPromptCompleted($db, $ticketId);
			
			$summaryUrls = [];
			foreach($result['images'] as $tempImg) {
				$s3Path = S3Helper::storeFileToS3(
        			"scans/{$scan->ticket_id}/summary",
					$tempImg
				);
				$summaryUrls[] = $s3Path;
				S3Helper::removeFileTemp($tempImg);
			}
			
			$scan->scanResult()->create([
				'summary' => $result['analysis']['summary'],
				'img_urls' => $summaryUrls
			]);
			
			return $result['analysis']['products'];
		} catch (\Throwable $e) {
			throw $e;
		} finally {
			S3Helper::removeFileTemp($userProfileImg);
			S3Helper::removeFileTemp($scanImg);
		}
	
    }
}
