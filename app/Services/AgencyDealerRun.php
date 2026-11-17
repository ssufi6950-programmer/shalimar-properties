<?php
namespace App\Services;

/** Session checkpoint only; execution advances by one exact ERP link per browser request. */
final class AgencyDealerRun
{
    public static function begin(array $links, int $actor): array
    {
        usort($links, static fn($a,$b) => (int)$a['id'] <=> (int)$b['id']);
        return ['token'=>bin2hex(random_bytes(24)), 'actor'=>$actor, 'expires'=>time()+7200,
            'links'=>$links, 'cursor'=>0, 'results'=>[], 'retry'=>[], 'retry_mode'=>false];
    }

    public static function advance(array &$run, callable $sync): void
    {
        $next = null;
        if ($run['retry_mode']) {
            $id = array_shift($run['retry']);
            foreach ($run['links'] as $link) if ((int)$link['id'] === $id) $next = $link;
        } else {
            foreach ($run['links'] as $link) if ((int)$link['id'] > $run['cursor']) { $next=$link; break; }
        }
        if ($next === null) return;
        $id=(int)$next['id'];
        $run['results'][$id]=$sync($next);
        if (!$run['retry_mode']) $run['cursor']=$id;
    }

    public static function progress(array $run): array
    {
        $sources=[]; $counts=['success'=>0,'failed'=>0,'skipped'=>0]; $issues=[]; $current=null;
        foreach ($run['links'] as $link) {
            $code=$link['source_code'];
            $sources[$code]??=['total'=>0,'processed'=>0,'success'=>0,'failed'=>0,'skipped'=>0,'dealers'=>0];
            $sources[$code]['total']++;
            $result=$run['results'][(int)$link['id']]??null;
            if (!$result) { $current??=$link['erp_agency_name'].' / '.$code; continue; }
            $state=$result['status']; $counts[$state]++; $sources[$code]['processed']++; $sources[$code][$state]++;
            $sources[$code]['dealers']+=(int)($result['count']??0);
            if ($state!=='success') $issues[]=['link_id'=>$link['id'],'agency'=>$link['erp_agency_name'],'source'=>$code,'message'=>$result['message']];
        }
        return ['token'=>$run['token'],'total'=>count($run['links']),'processed'=>count($run['results']),
            'counts'=>$counts,'sources'=>$sources,'issues'=>$issues,'current'=>$current,
            'done'=>$run['retry_mode'] ? !$run['retry'] : count($run['results'])===count($run['links'])];
    }
}
