<?php

namespace Rxkk\Lib\Tool;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Rxkk\Lib\Env;
use Rxkk\Lib\Logger\Logger;

class Gitlab {

    private LoggerInterface $logger;

    public function __construct(
        public ?string $gitlabUrl    = null,
        public ?string $gitlabToken  = null,
    ) {
        $this->gitlabUrl ??= Env::get('GITLAB_URL');
        $this->gitlabToken ??= Env::get('GITLAB_API_TOKEN');
        $this->logger = Logger::getLogger('rxkk')->withName('rxkk.gitlab');
    }

    /**
     * @param $url - like: https://gitlab.com/namespace/project/-/merge_requests/7687
     * @return string
     */
    public function getMergeRequestDiffByUrl($url) {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null) {
            throw new InvalidArgumentException("Invalid URL: {$url}");
        }

        // trim leading slash and split
        $trimmed = ltrim($path, '/');
        // explode give [ 'namespace/project', '1234' ]
        $parts = explode('/-/merge_requests/', $trimmed, 2);
        if (count($parts) !== 2 || !is_numeric($parts[1])) {
            throw new InvalidArgumentException("Invalid MR URL format: {$url}");
        }

        $projectId = rawurlencode($parts[0]);
        $mrIid = (int)$parts[1];
        return $this->getMergeRequestDiff($projectId, $mrIid);
    }

    /** Получение diff по merge request */
    public function getMergeRequestDiff($projectId, $mrIid) {
        $url = "/api/v4/projects/{$projectId}/merge_requests/{$mrIid}/diffs";
        $data = $this->curlGitlab($url);

        $diffText = '';

        foreach ($data as $diff) {

            if ($diff['new_path'] === $diff['old_path']) {
                $diffText .= "File: {$diff['new_path']}\n";
            } else {
                $diffText .= "File: {$diff['old_path']} -> {$diff['new_path']}\n";
            }

            if ($diff['new_file']) {
                $diffText .= "It's a new file\n";
            }

            if ($diff['deleted_file']) {
                $diffText .= "We deleted this file\n";
            }

            if ($diff['renamed_file']) {
                $diffText .= "We renamed this file\n";
            }

            if ($diff['diff']) {
                $diffText .= "Diff: \n```\n" . rtrim($diff['diff']) . "\n```\n\n";
            }
        }

        return rtrim($diffText);
    }

    private function curlGitlab(string $url, array $data = []) {
        $fullUrl = $this->gitlabUrl . $url;
        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["PRIVATE-TOKEN: $this->gitlabToken"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        if ($data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $this->logger->info("curl {$fullUrl}", $data);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->logger->debug('curl result', [
            'httpCode' => $httpCode,
            'result' => $result,
        ]);

        curl_close($ch);
        if ($httpCode !== 200) {
            return [];
        }
        $mergeRequests = json_decode($result, true);
        return $mergeRequests;
    }
}