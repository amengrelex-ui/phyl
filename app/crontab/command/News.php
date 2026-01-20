<?php

namespace app\crontab\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\Db;

class News extends Command
{
    protected function configure()
    {
        $this->setName('news')
            ->setDescription('定时计划：新闻定时任务');
    }

    protected function execute(Input $input, Output $output)
    {
        $journalism_id = Db::name('api_journalism')
            ->where('delete_time IS NULL')
            ->column('id');

        $list_func = function ($url) {
            $html = file_get_contents($url);
            $htmlOneLine = preg_replace("/\r|\n|\t|\40/", "", $html);
            preg_match_all("/<liclass=\"mc_e1_li\">(.*)<\/li>/iU", $htmlOneLine, $data);

            $id_array = [];
            foreach ($data[1] as $item) {
                preg_match("/<ahref=\"\/news\/(.*).html\"/iU", $item, $temp);
                $id_array[] = $temp[1];
            }

            return $id_array;
        };
        $detail_func = function ($url) {
            $html = file_get_contents($url);
//                $htmlOneLine = preg_replace("/\r|\n|\t|\40/", "", $html);
            $htmlOneLine = preg_replace("/\r|\n|\t/", "", $html);
//                preg_match("/<h1class=\"mc_e3s1_title\">(.*)<\/h1>/iU", $htmlOneLine, $title);
            preg_match("/<h1 class=\"mc_e3s1_title\">(.*)<\/h1>/iU", $htmlOneLine, $title);

            if (!empty($title[1])) {
                $title = $title[1];
            }

//                preg_match("/<divclass=\"mc_e3s1b_txtboxyxedr_active\">(.*)<\/div>/iU", $htmlOneLine, $content);
            preg_match("/<div class=\"mc_e3s1b_txtbox yxedr_active\">(.*)<\/div>/iU", $htmlOneLine, $content);
            if (!empty($content[1])) {
                $content = $content[1];
            }

            return [
                'title'   => $title,
                'content' => $content,
            ];
        };
        $id_array = $list_func("https://www.catl.com/news/");
//        $id_array = $list_func('新闻1.mhtml');

        $index = 1;
        while (true) {
            $index++;
            $temp = $list_func("https://www.catl.com/news/index_{$index}.html");
//            $temp = $list_func("新闻{$index}.mhtml");
            if (empty($temp)) {
                break;
            }

            $id_array = array_merge($id_array, $temp);
        }

        $time = date('Y-m-d H:i:s');
        $success = 0;
        foreach ($id_array as $item) {
//                $temp = $detail_func("详情{$item}.mhtml");
            $temp = $detail_func("https://www.catl.com/news/{$item}.html");
            if (empty($temp['title']) || empty($temp['content'])) {
                continue;
            }

            if (in_array($item, $journalism_id)) {
                $result = Db::name('api_journalism')
                    ->where('id', $item)
                    ->update(
                        [
                            'id'          => $item,
                            'title'       => $temp['title'],
                            'content'     => $temp['content'],
                            'update_time' => $time,
                        ]
                    );
            } else {
                $result = Db::name('api_journalism')
                    ->insert(
                        [
                            'id'          => $item,
                            'title'       => $temp['title'],
                            'content'     => $temp['content'],
                            'image'       => '',
                            'create_time' => $time,
                            'update_time' => $time,
                        ]
                    );
            }

            if ($result) {
                $success++;
            }
        }
        dump($success);
    }
}
