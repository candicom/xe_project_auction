<?php
    /**
     * @class  paAPI
     * @author sol(sol@ngleader.com)
     * @brief  pa module View Action에 대한 API 처리
     **/

    class paAPI extends pa {

/* do not use dispPaContent .
        function dispPaContent(&$oModule) {
        }
*/

        /**
         * @brief notice list
         **/
        function dispPaNoticeList(&$oModule) {
             $oModule->add('notice_list',$this->arrangeContentList(Context::get('notice_list')));
        }


        /**
         * @brief content list
         **/
        function dispPaContentList(&$oModule) {
			$api_type = Context::get('api_type');
            $document_list = $this->arrangeContentList(Context::get('document_list'));
			
			if($api_type =='summary')
			{
				$content_cut_size = Context::get('content_cut_size');
				$content_cut_size = $content_cut_size?$content_cut_size:50;
				foreach($document_list as $k=>$v)
				{
					$oDocument = new documentItem();
					$oDocument->setAttribute($v, false);
					$document_list[$k]->content = $oDocument->getSummary($content_cut_size);
					unset($oDocument);
				}
			}

            $oModule->add('document_list',$document_list);
            $oModule->add('page_navigation',Context::get('page_navigation'));
        }


        /**
         * @brief category list
         **/
        function dispPaCatogoryList(&$oModule) {
            $oModule->add('category_list',Context::get('category_list'));
        }

        /**
         * @brief pa content view
         **/
        function dispPaContentView(&$oModule) {
            $oDocument = Context::get('oDocument');
            $extra_vars = $oDocument->getExtraVars();
            $oDocument->add('extra_vars',$this->arrangeExtraVars($extra_vars));
            $oModule->add('oDocument',$this->arrangeContent($oDocument));
        }


        /**
         * @brief contents file list
         **/
        function dispPaContentFileList(&$oModule) {
            $oModule->add('file_list',$this->arrangeFile(Context::get('file_list')));
        }


        /**
         * @brief tag list
         **/
        function dispPaTagList(&$oModule) {
            $oModule->add('tag_list',Context::get('tag_list'));
        }

        /**
         * @brief comments list
         **/
        function dispPaContentCommentList(&$oModule) {
            $oModule->add('comment_list',$this->arrangeComment(Context::get('comment_list')));
        }

        function arrangeContentList($content_list) {
            $output = array();
            if(count($content_list)) {
                foreach($content_list as $key => $val) $output[] = $this->arrangeContent($val);
            }
            return $output;
        }


        function arrangeContent($content) {
            $output = null;
            if($content){			
                $output = $content->gets('document_srl','category_srl','member_srl','nick_name','user_id','user_name','title','content','tags','readed_count','voted_count','blamed_count','comment_count','regdate','last_update','extra_vars','status');
				
				$t_width  = Context::get('thumbnail_width');
				$t_height = Context::get('thumbnail_height');
				$t_type   = Context::get('thumbnail_type');

				if ($t_width && $t_height && $t_type && $content->thumbnailExists($t_width, $t_height, $t_type)) {
					$output->thumbnail_src = $content->getThumbnail($t_width, $t_height, $t_type);
				}
            }
            return $output;
        }

        function arrangeComment($comment_list) {
            $output = array();
            if(count($comment_list) > 0 ) {
                foreach($comment_list as $key => $val){
                    $item = null;
                    $item = $val->gets('comment_srl','parent_srl','depth','is_secret','content','voted_count','blamed_count','user_id','user_name','nick_name','email_address','homepage','regdate','last_update');
                    $output[] = $item;
                }
            }
            return $output;
        }


        function arrangeFile($file_list) {
            $output = array();
            if(count($file_list) > 0) {
                foreach($file_list as $key => $val){
                    $item = null;
                    $item->sid = $val->sid;
                    $item->download_count = $val->download_count;
                    $item->source_filename = $val->source_filename;
                    $item->uploaded_filename = $val->uploaded_filename;
                    $item->file_size = $val->file_size;
                    $item->regdate = $val->regdate;
                    $item->download_url = $val->download_url;
                    $output[] = $item;
                }
            }
            return $output;
        }

        function arrangeExtraVars($list) {
            $output = array();
            if(count($list)) {
                foreach($list as $key => $val){
                    $item = null;
                    $item->name = $val->name;
                    $item->type = $val->type;
                    $item->desc = $val->desc;
                    $item->value = $val->value;
                    $output[] = $item;
                }
            }
            return $output;
        }
    }
?>
