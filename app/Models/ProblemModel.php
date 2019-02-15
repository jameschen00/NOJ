<?php

namespace App\Models;

use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProblemModel extends Model
{
    protected $tableName = 'problem';

    public function detail($pcode)
    {
        $prob_detail = DB::table($this->tableName)->where("pcode", $pcode)->first();
        // [Depreciated] Joint Query was depreciated here for code maintenance reasons
        if (!is_null($prob_detail)) {
            $prob_detail["parsed"] = [
                "description"=>Markdown::convertToHtml($prob_detail["description"]),
                "input"=>Markdown::convertToHtml($prob_detail["input"]),
                "output"=>Markdown::convertToHtml($prob_detail["output"]),
                "note"=>Markdown::convertToHtml($prob_detail["note"])
            ];
            $prob_detail["update_date"]=date_format(date_create($prob_detail["update_date"]),'m/d/Y H:i:s');
            $prob_detail["oj_detail"] = DB::table("oj")->where("oid", $prob_detail["OJ"])->first();
            $prob_detail["samples"] = DB::table("problem_sample")->where("pid", $prob_detail["pid"])->get()->all();
            $prob_detail["tags"] = DB::table("problem_tag")->where("pid", $prob_detail["pid"])->get()->all();
            $prob_stat = DB::table("submission")->select(
                DB::raw("count(sid) as submission_count"),
                DB::raw("sum(verdict='accepted') as passed_count"),
                DB::raw("sum(verdict='accepted')/count(sid)*100 as ac_rate")
            )->where(["pid"=>$prob_detail["pid"]])->first();
            if($prob_stat["submission_count"]==0){
                $prob_detail["submission_count"]=0;
                $prob_detail["passed_count"]=0;
                $prob_detail["ac_rate"]=0;
            }else{
                $prob_detail["submission_count"]=$prob_stat["submission_count"];
                $prob_detail["passed_count"]=$prob_stat["passed_count"];
                $prob_detail["ac_rate"]=round($prob_stat["ac_rate"],2);
            }
        }
        return $prob_detail;
    }

    public function list()
    {
        $prob_list = DB::table($this->tableName)->select("pid","pcode","title")->get()->all(); // return a array
        // [ToDo] Paging required
        foreach($prob_list as &$p) {
            $prob_stat = DB::table("submission")->select(
                DB::raw("count(sid) as submission_count"),
                DB::raw("sum(verdict='accepted') as passed_count"),
                DB::raw("sum(verdict='accepted')/count(sid)*100 as ac_rate")
            )->where(["pid"=>$p["pid"]])->first();
            if($prob_stat["submission_count"]==0){
                $p["submission_count"]=0;
                $p["passed_count"]=0;
                $p["ac_rate"]=0;
            }else{
                $p["submission_count"]=$prob_stat["submission_count"];
                $p["passed_count"]=$prob_stat["passed_count"];
                $p["ac_rate"]=round($prob_stat["ac_rate"],2);
            }
        }
        return $prob_list;
    }

    public function pid($pcode)
    {
        $temp = DB::table($this->tableName)->where(["pcode"=>$pcode])->select("pid")->first();
        return empty($temp) ? 0 : $temp["pid"];
    }

    public function clearTags($pid)
    {
        DB::table("problem_tag")->where(["pid"=>$pid])->delete();
        return true;
    }

    public function addTags($pid,$tag)
    {
        DB::table("problem_tag")->insert(["pid"=>$pid,"tag"=>$tag]);
        return true;
    }

    public function getSolvedCount($oid){
        return DB::table($this->tableName)->select("pid","solved_count")->where(["OJ"=>$oid])->get()->all();
    }

    public function updateDifficulty($pid,$diff_level){
        DB::table("problem_tag")->where(["pid"=>$pid])->update(["difficulty"=>$diff_level]);
        return true;
    }

    public function insertProblem($data){
        $pid = DB::table($this->tableName)->insertGetId([
            'difficulty'=>-1,
            'file'=>$data['file'],
            'title'=>$data['title'],
            'time_limit'=>$data['time_limit'],
            'memory_limit'=>$data['memory_limit'],
            'OJ'=>$data['OJ'],
            'description'=>$data['description'],
            'input'=>$data['input'],
            'output'=>$data['output'],
            'note'=>$data['note'],
            'input_type'=>$data['input_type'],
            'output_type'=>$data['output_type'],
            'pcode'=>$data['pcode'],
            'contest_id'=>$data['contest_id'],
            'index_id'=>$data['index_id'],
            'origin'=>$data['origin'],
            'source'=>$data['source'],
            'solved_count'=>$data['solved_count'],
            'update_date'=>date("Y-m-d H:i:s")
        ]);

        if (!empty($data["sample"])) {
            foreach ($data["sample"] as $d) {
                DB::table("problem_sample")->insert([
                    'pid'=>$pid,
                    'sample_input'=>$d['sample_input'],
                    'sample_output'=>$d['sample_output'],
                ]);
            }
        }

        return $pid;
    }

    public function updateProblem($data){
        DB::table($this->tableName)->where(["pcode"=>$data['pcode']])->update([
            'difficulty'=>-1,
            'file'=>$data['file'],
            'title'=>$data['title'],
            'time_limit'=>$data['time_limit'],
            'memory_limit'=>$data['memory_limit'],
            'OJ'=>$data['OJ'],
            'description'=>$data['description'],
            'input'=>$data['input'],
            'output'=>$data['output'],
            'note'=>$data['note'],
            'input_type'=>$data['input_type'],
            'output_type'=>$data['output_type'],
            'contest_id'=>$data['contest_id'],
            'index_id'=>$data['index_id'],
            'origin'=>$data['origin'],
            'source'=>$data['source'],
            'solved_count'=>$data['solved_count'],
            'update_date'=>date("Y-m-d H:i:s")
        ]);

        $pid=$this->pid($data['pcode']);

        DB::table("problem_sample")->where(["pid"=>$pid])->delete();

        if (!empty($data["sample"])) {
            foreach ($data["sample"] as $d) {
                DB::table("problem_sample")->insert([
                    'pid'=>$pid,
                    'sample_input'=>$d['sample_input'],
                    'sample_output'=>$d['sample_output'],
                ]);
            }
        }

        return $pid;
    }
}