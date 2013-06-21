<?php
    /**
     * @class  paView
     * @author kimdongmin (kdm3843@gmail.com)
     * @brief  pa module View class
     **/

    class paView extends pa {
		var $listConfig;
		var $columnList;

        /**
         * @brief initialization
         * pa module can be used in either normal mode or admin mode.\n
         **/
        function init() {

            // 1. 인자로 사용될 넘어온 각 파라미터들의 특수문자를 encodeHTML 을 통해 lang 을 치환하고 html, js 관련 특수문자를 치환해준다.
            // 2. 리스트수, 검색리스트수, 페이지수, 공지사항최상단보여주기,상태리스트,상담기능 등 기본정보 셋팅.
            // 3. 스킨경로 셋팅.
            // 4. 값이 없는 확장변수 리스트 셋팅.
            // 5. 정렬을 위한 order_target 셋팅.
            // 6. 필터, 자바스크립트 파일 추가.
            // 7. [document_srl]_c_page 제거?

			$oSecurity = new Security();
			$oSecurity->encodeHTML('document_srl', 'comment_srl', 'vid', 'mid', 'page', 'category', 'search_target', 'search_keyword', 'sort_index', 'order_type', 'trackback_srl');

            /**
             * 기본 모듈 정보들 설정
             **/
            if($this->module_info->list_count) $this->list_count = $this->module_info->list_count;
            if($this->module_info->search_list_count) $this->search_list_count = $this->module_info->search_list_count;
            if($this->module_info->page_count) $this->page_count = $this->module_info->page_count;
            $this->except_notice = $this->module_info->except_notice == 'N' ? false : true;

			// $this->_getStatusNameList secret option backward compatibility
			$oDocumentModel = &getModel('document');
			$statusList = $this->_getStatusNameList($oDocumentModel);
            // statusList['SECRET'] 이 있다면 이 모듈은 secret 글쓰기가 가능함.
			if(isset($statusList['SECRET']))
			{
				$this->module_info->secret = 'Y';
			}
			
			//If category are exsist, set value 'use_category' to 'Y'
			if($this->module_info->hide_category != 'Y' && count($oDocumentModel->getCategoryList($this->module_info->module_srl)))
				$this->module_info->use_category = 'Y';
			else
				$this->module_info->use_category = 'N';

            /**
             * 상담기능 체크, 관리자라면 상담기능 off
             * 로그인 하지 않았다면, 글쓰기, 댓글쓰기, 글 보기 false
             **/
            if($this->module_info->consultation == 'Y' && !$this->grant->manager) {
                $this->consultation = true; 
                if(!Context::get('is_logged')) $this->grant->list = $this->grant->write_document = $this->grant->write_comment = $this->grant->view = false;
            } else {
                $this->consultation = false;
            }

            /**
             * 스킨 경로를 미리 template_path 라는 변수로 설정함
             **/
            $template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
            if(!is_dir($template_path)||!$this->module_info->skin) {
                $this->module_info->skin = 'default';
                $template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
            }
            $this->setTemplatePath($template_path);

            /**
             * 확장변수 셋팅.
             **/
            $oDocumentModel = &getModel('document');
            $extra_keys = $oDocumentModel->getExtraKeys($this->module_info->module_srl);
            Context::set('extra_keys', $extra_keys);

			/**
			 * sorting을 위한 order_target에 확장변수도 추가.
			 **/
			if (is_array($extra_keys)){
				foreach($extra_keys as $val){
					$this->order_target[] = $val->eid;
				}
			}
            /** 
             * load javascript, JS filters
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'input_password.xml');
            Context::addJsFile($this->module_path.'tpl/js/pa.js');

			// remove [document_srl]_cpage from get_vars
			$args = Context::getRequestVars();
			foreach($args as $name => $value)
			{
				if(preg_match('/[0-9]+_cpage/', $name))
				{
					Context::set($name, '', TRUE);
					Context::set($name, $value);
				}
			}
        }

        /**
         * @brief display pa category_list
         **/
        function dispPaCategoryListWall() {

            $this->dispPaCategoryList();

            /**
             * display the search options on the screen
             * add extra vaiables to the search options
             **/
            // use search options on the template (the search options key has been declared, based on the language selected)
            foreach($this->search_option as $opt) $search_option[$opt] = Context::getLang($opt);
            $extra_keys = Context::get('extra_keys');
            if($extra_keys) {
                foreach($extra_keys as $key => $val) {
                    if($val->search == 'Y') $search_option['extra_vars'.$val->idx] = $val->name;
                }
            }
            Context::set('search_option', $search_option);

            Context::addJsFilter($this->module_path.'tpl/filter', 'search.xml');

            $oSecurity = new Security();
            $oSecurity->encodeHTML('search_option.');

            $this->setTemplateFile('pa_category_list');

        }


        /**
         * @brief display pa contents
         **/
        function dispPaContent() {
            /**
             * check the access grant (all the grant has been set by the module object)
             **/
            if(!$this->grant->access || !$this->grant->list) return $this->dispPaMessage('msg_not_permitted');

            /**
             * display the category list, and then setup the category list on context
             **/
            $this->dispPaCategoryList();

            /**
             * display the search options on the screen
             * add extra vaiables to the search options
             **/
            // use search options on the template (the search options key has been declared, based on the language selected)
            foreach($this->search_option as $opt) $search_option[$opt] = Context::getLang($opt);
            $extra_keys = Context::get('extra_keys');
            if($extra_keys) {
                foreach($extra_keys as $key => $val) {
                    if($val->search == 'Y') $search_option['extra_vars'.$val->idx] = $val->name;
                }
            }
            Context::set('search_option', $search_option);

			$oDocumentModel = &getModel('document');
			$statusNameList = $this->_getStatusNameList($oDocumentModel);
			if(count($statusNameList) > 0) Context::set('status_list', $statusNameList);

            // display the pa content
            $this->dispPaContentView();

			// list config, columnList setting
            $oPaModel = &getModel('pa');
			$this->listConfig = $oPaModel->getListConfig($this->module_info->module_srl);
			$this->_makeListColumnList();

            // display the notice list
            $this->dispPaNoticeList();

            // list
            $this->dispPaContentList();

            /**
             * add javascript filters
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'search.xml');

			$oSecurity = new Security();
			$oSecurity->encodeHTML('search_option.');

            // setup the tmeplate file
            $this->setTemplateFile('list');
        }

        /**
         * @brief display the category list
         **/
        function dispPaCategoryList(){
            // check if the use_category option is enabled
            if($this->module_info->use_category=='Y') {
                $oDocumentModel = &getModel('document');
                Context::set('category_list', $oDocumentModel->getCategoryList($this->module_srl));
				
                $oSecurity = new Security();
				$oSecurity->encodeHTML('category_list.', 'category_list.childs.');
            }
        }

        /**
         * @brief display the pa conent view
         **/
        function dispPaContentView(){
            // get the variable value
            $document_srl = Context::get('document_srl');
            $page = Context::get('page');

            // generate document model object
            $oDocumentModel = &getModel('document');
            $oPaModel = &getModel('pa');

            $logged_info = Context::get('logged_info');
            /**
             * if the document exists, then get the document information
             **/
            if($document_srl) {
                $oDocument = $oDocumentModel->getDocument($document_srl, false, true); 

                // if the document is existed
                if($oDocument->isExists()) {

                    // if the module srl is not consistent
                    if($oDocument->get('module_srl')!=$this->module_info->module_srl ) return $this->stop('msg_invalid_request');

                    // check the manage grant
                    if($this->grant->manager) $oDocument->setGrant();

                    // if the consultation function is enabled, and the document is not a notice
                    if($this->consultation && !$oDocument->isNotice()) {
                        if($oDocument->get('member_srl')!=$logged_info->member_srl) $oDocument = $oDocumentModel->getDocument(0);
                    }

                // if the document is not existed, then alert a warning message
                } else {
                    Context::set('document_srl','',true);
                    $this->alertMessage('msg_not_founded');
                }

            /**
             * if the document is not existed, get an empty document
             **/
            } else {
                $oDocument = $oDocumentModel->getDocument(0);
            }

            /**
             *check the document view grant, isGranted() 는 관리자 권한여부. $this->grant->view 는 보기 권한.
             **/
            if($oDocument->isExists()) {

                // 프로젝트 정보 셋팅.
                $project = $oPaModel->getProject($oDocument->document_srl);
                $bids = $oPaModel->getBidList($oDocument->document_srl);

                // 입찰 정보 셋팅.
                $is_already_bid = false;
                $oMemberModel = &getModel('member');
                if(!empty($bids))
                foreach($bids as $val) {
                    if(!$logged_info->is_admin) {
                        $val->ipaddress = preg_replace('/([0-9]+)\.([0-9]+)\.([0-9]+)\.([0-9]+)/', '*.$2.$3.$4', $val->ipaddress);
                    }
                    if($val->member_srl==$logged_info->member_srl) $is_already_bid = true;
                    $val->nick_name = $oMemberModel->getMemberInfoByMemberSrl($val->member_srl)->nick_name;
                    unset($profile_info);
                    $profile_info = $oMemberModel->getProfileImage($val->member_srl);
                    if(!$profile_info) {

                    }
                    else {
                        $val->profile_image = $profile_info->src;
                    }
                }

                // 로그인한 사람이 채택되었는지 판단.
                $is_awarded = false;
                if($is_already_bid)
                {
                    $awarded_bids = $oPaModel->getBidListAwarded($oDocument->document_srl);
                    if(!empty($awarded_bids))
                    foreach($awarded_bids as $val)
                    {
                        if($val->member_srl==$logged_info->member_srl) $is_awarded = true;
                    }
                }

                // 관리자가 아니고 view 권한이 없다면 권한 없음.
                if(!$this->grant->view && !$oDocument->isGranted())
                {
                    $oDocument = $oDocumentModel->getDocument(0);
                    Context::set('document_srl','',true);
                    $this->alertMessage('msg_not_permitted');
                }
                else
                {
                    if($project->project_status=='WORKING')
                    {   // 진행중 : 채택된 사람과 (등록한 사람, 관리자)만 볼 수 있음.
                        if(!$is_awarded && !$oDocument->isGranted()) {
                            $oDocument = $oDocumentModel->getDocument(0);
                            Context::set('document_srl','',true);
                            $this->alertMessage('msg_not_permitted');
                        }
                    }
                    else if($project->project_status=='COMPLETE')
                    {   // 완료 : 채택된 사람과 (등록한 사람, 관리자)만 볼 수 있음.
                        if(!$is_awarded && !$oDocument->isGranted()) {
                            $oDocument = $oDocumentModel->getDocument(0);
                            Context::set('document_srl','',true);
                            $this->alertMessage('msg_not_permitted');
                        }
                    }
                    else if($project->project_status=='CANCEL')
                    {   // 취소 : (등록한 사람, 관리자)만 볼 수 있음.
                        if(!$oDocument->isGranted()) {
                            $oDocument = $oDocumentModel->getDocument(0);
                            Context::set('document_srl','',true);
                            $this->alertMessage('msg_not_permitted');
                        }
                    }
                    else if($project->project_status=='EXPIRE')
                    {   // 기간만료 : (등록한 사람, 관리자)만 볼 수 있음.
                        if(!$oDocument->isGranted()) {
                            $oDocument = $oDocumentModel->getDocument(0);
                            Context::set('document_srl','',true);
                            $this->alertMessage('msg_not_permitted');
                        }
                    }
                    else
                    {   // 입찰가능 : ...

                    }

                    // add the document title to the browser
                    Context::addBrowserTitle($oDocument->getTitleText());

                    // update the document view count (if the document is not secret)
                    if(!$oDocument->isSecret() || $oDocument->isGranted()) $oDocument->updateReadedCount();

                    // disappear the document if it is secret
                    if($oDocument->isSecret() && !$oDocument->isGranted()) $oDocument->add('content',Context::getLang('thisissecret'));
                }
            }

            // setup the document object on context
            $oDocument->add('module_srl', $this->module_srl);

            Context::set('oDocument', $oDocument);
            Context::set('project', $project);
            Context::set('is_already_bid', $is_already_bid);
            Context::set('bids', $bids);

            /** 
             * add javascript filters
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');
            Context::addJsFilter($this->module_path.'tpl/filter','insert_bid.xml');
            Context::addJsFilter($this->module_path.'tpl/filter','delete_bid.xml');
            Context::addJsFilter($this->module_path.'tpl/filter','award_bid.xml');
            Context::addJsFilter($this->module_path.'tpl/filter','revoke_bid.xml');
        
//            return new Object();
        }

        /**
         * @brief  display the document file list (can be used by API)
         **/
        function dispPaContentFileList(){
            $oDocumentModel = &getModel('document');
            $document_srl = Context::get('document_srl');
            $oDocument = $oDocumentModel->getDocument($document_srl);
            Context::set('file_list',$oDocument->getUploadedFiles());

			$oSecurity = new Security();
			$oSecurity->encodeHTML('file_list..source_filename');
        }

        /**
         * @brief display the document comment list (can be used by API)
         **/
        function dispPaContentCommentList(){
            $oDocumentModel = &getModel('document');
            $document_srl = Context::get('document_srl');
            $oDocument = $oDocumentModel->getDocument($document_srl);
            $comment_list = $oDocument->getComments();

            // setup the comment list
			if(is_array($comment_list))
			{
				foreach($comment_list as $key => $val){
					if(!$val->isAccessible()){
						$val->add('content',Context::getLang('thisissecret'));
					}
				}
			}
            Context::set('comment_list',$comment_list);

        }

        /**
         * @brief display notice list (can be used by API)
         **/
        function dispPaNoticeList(){
            $oDocumentModel = &getModel('document');
            $args = new stdClass();
            $args->module_srl = $this->module_srl;
            $notice_output = $oDocumentModel->getNoticeList($args, $this->columnList);

            // document_srl(공통), module_srl(공통), category_srl(공통), member_srl(공통),
            // title(공통), content(공통), project_duration, project_type, budget, skill_required, project_status,
            // bid_count(자동), bid_average(자동), started, ends, is_featured, is_deleted(삭제로직)
            
            $oPaModel = &getModel('pa');
            if(!empty($notice_output->data))
            foreach($notice_output->data as $key => $val) {
                unset($project);
                $project = $oPaModel->getProject($val->document_srl);
                //if($val->project->project_status!='OPEN') unset($notice_output->data[$key]);
                // 두번이나 반복할 필요는 없다.
                //if(!$this->processPostendProject($project, $val)) return new Object('-1', 'error_process_postend_project');
                if($project->ends < date('YmdHis')) {
                    unset($notice_output->data[$key]);
                }
                else {
                    $val->project = $project;
                }

            }

            Context::set('notice_list', $notice_output->data);
        }

        /**
         * @brief display pa content list
         **/
        function dispPaContentList(){
            // check the grant
            if(!$this->grant->list) {
                Context::set('document_list', array());
                Context::set('total_count', 0);
                Context::set('total_page', 1);
                Context::set('page', 1);
                Context::set('page_navigation', new PageHandler(0,0,1,10));
                return;
            }

            $oDocumentModel = &getModel('document');

            // setup module_srl/page number/ list number/ page count
            $args = new stdClass();
            $args->module_srl = $this->module_srl; 
            $args->page = Context::get('page');
            $args->list_count = $this->list_count; 
            $args->page_count = $this->page_count; 

            // get the search target and keyword
            $args->search_target = Context::get('search_target'); 
            $args->search_keyword = Context::get('search_keyword'); 

            // if the category is enabled, then get the category
            if($this->module_info->use_category=='Y') $args->category_srl = Context::get('category'); 

            // setup the sort index and order index
            $args->sort_index = Context::get('sort_index');
            $args->order_type = Context::get('order_type');
            if(!in_array($args->sort_index, $this->order_target)) $args->sort_index = $this->module_info->order_target?$this->module_info->order_target:'list_order';
            if(!in_array($args->order_type, array('asc','desc'))) $args->order_type = $this->module_info->order_type?$this->module_info->order_type:'asc';

            // set the current page of documents
            $_get = $_GET;
            if(!$args->page && ($_GET['document_srl'] || $_GET['entry'])) {
                $oDocument = $oDocumentModel->getDocument(Context::get('document_srl'));
                if($oDocument->isExists() && !$oDocument->isNotice()) {
                    // 만약 document_srl 이나 entry 가 존재하면 위의 정보들을 바탕으로 해당 문서의 페이지를 구함.
                    $page = $oDocumentModel->getDocumentPage($oDocument, $args);
                    Context::set('page', $page);
                    $args->page = $page;
                }
            }

            // setup the list count to be serach list count, if the category or search keyword has been set
            if($args->category_srl || $args->search_keyword) $args->list_count = $this->search_list_count;

            // if the consultation function is enabled,  the get the logged user information
            if($this->consultation) {
                $logged_info = Context::get('logged_info');
                $args->member_srl = $logged_info->member_srl;
            }

            // setup the list config variable on context
            Context::set('list_config', $this->listConfig);

            // columList 에는 is_notice 정보가 없다.
            $columnList = $this->columnList;
            array_push($columnList, 'documents.is_notice');

            // setup document list variables on context 
            $output = $oDocumentModel->getDocumentList($args, $this->except_notice, true, $columnList);

            // document_srl(공통), module_srl(공통), category_srl(공통), member_srl(공통),
            // title(공통), content(공통), project_duration, project_type, budget, skill_required, project_status,
            // bid_count(자동), bid_average(자동), started, ends, is_featured, is_deleted(삭제로직)

            $oPaModel = &getModel('pa');
            if(!empty($output->data))
            foreach($output->data as $key => $val) {
                unset($project);
                // 배열의 경우 key 값은 key 값 대로 가지고 순서는 순서대로 가진다. key 값이 순서는 아니다.
                // $val->project 와 같이 추가하는 건 $output->data 에도 추가가 되는데...
                $project = $oPaModel->getProject($val->document_srl);
                // unset($val) 의 경우에는 $output->data 에서 제거가 되지 않네...
                // 배열의 foreach 문에 $val 는 배열의 요소이고 참조자를 가져오는 개념.
                // 참조자에 값을 추가하면 원래의 배열에 추가가 되지만,
                // unset 과 같이 값을 release 시킬 경우는 복사된 메모리 주소를 release 시키므로 원래의 메모리 주소 및 데이터는 남아있다.
                // 따라서 unset($output->data[$key]) 과 같이 원래의 값을 release 시켜주어야 한다.
                // OPEN, WORKING, COMPLETE, CANCEL, EXPIRE
                //if($val->project->project_status!='OPEN') unset($output->data[$key]);
                if(!$this->processPostendProject($project, $val)) return new Object('-1', 'error_process_postend_project');
                //if($project->is_deleted=="N")
                //if(!$this->processThreemonthProject($project, $val)) return new Object('-1', 'error_process_threemonth_project');
                $val->project = $project;
            }


            Context::set('document_list', $output->data);
            Context::set('total_count', $output->total_count);
            Context::set('total_page', $output->total_page);
            Context::set('page', $output->page);
            Context::set('page_navigation', $output->page_navigation);
        }

        // 7일(ends) 후에는 추천 프로젝트에서 없앰.
        // 7일(ends)이 지나고 채택된 입찰이 하나도 없을 경우 기간만료.
        // 7일(ends)이 지나고 채택된 입찰이 있다면 WORKING. (list 로직에 항상 처리.)
        function processPostendProject(&$project, &$oDocument)
        {
            $oPaModel = &getModel('pa');

            // columnList 를 넣어주었으니깐 getDocumentList, getNoticeList 에서 가져온 리스트에는 is_notice 정보가 없다...
            // 마감일이 지난 프로젝트들에 대해서만 ...
            if($project->ends < date('YmdHis')) {
                // 추천 프로젝트 상태를 변경.
                $project->is_featured = 'N';

                // 문서의 notice 도 N 으로 변경.
                if($oDocument->get('is_notice')!='N') {
                    $args = new stdClass();
                    $args->document_srl = $oDocument->document_srl;
                    $args->is_notice = 'N';
                    //$oDocument->set('is_notice', 'N');
                    // updateDocument 에서 원본(document_srl 만 참고함), 바꿀내용의 document, csrf 공격판단(true이면 csrf로 취급을 안함)
                    // $output = $oDocumentController->updateDocument($oDocument_src, $oDocument, TRUE);
                    // 수정해야겠다. $oDocumentController 의 updateDocument 의 경우 documentItem 을 인자로 하지 않아서 $obj 에 필요인자를 다 채워주어야하고 복잡함.
                    $output = executeQuery('pa.updateDocumentNotice', $args);
                }
                // OPEN, WORKING, COMPLETE, CANCEL, EXPIRE 중 OPEN 상태인 것들만 처리.
                if($project->project_status=='OPEN') {
                    // 채택된 입찰이 있다면 WORKING, 없다면 EXPIRE
                    $bid_list_awarded = $oPaModel->getBidListAwarded($project->document_srl);
                    if(!empty($bid_list_awarded))
                    {
                        $project->project_status = 'WORKING';
                    }
                    else
                    {
                        $project->project_status = 'EXPIRE';
                    }

                    $output = executeQuery('pa.updateProject', $project);

                    if(!$output->toBool()) return false;
                }
            }

            return true;
        }

        // 3개월 지난 프로젝트의 경우 휴지통으로... project와 bid 도 제거해야함. project는 is_deleted. bid 는 남겨둠.
        function processThreemonthProject(&$project, &$oDocument)
        {
            // OPEN, WORKING, COMPLETE, CANCEL, EXPIRE 중 WORKING 이 아닌 상태인 것들만 처리.
            if($project->project_status!='WORKING') {
                if(strtotime("+3 month", strtotime($project->ends)) < time()) {
                    $args = new stdClass();

                    $oDB = &DB::getInstance();
                    $oDB->begin();

                    // 프로젝트 문서를 휴지통으로 ...
                    $args->document_srl = $oDocument->document_srl;
                    $args->description = "3 months passed project";
                    $output = $this->moveDocumentToTrash($args);
                    if(!$output || !$output->toBool()) return false;

                    // 프로젝트를 is_deleted 로 ...
                    unset($output);
                    $project->is_deleted = "Y";
                    $output = executeQuery('pa.updateProject', $project);
                    if(!$output->toBool()) return false;

                    // 프로젝트에 종속된 입찰들은 그대로 둠. 나중에 마이그레이션 툴로 해결.

                    $oDB->commit();
                }
            }

            return true;
        }


		function _makeListColumnList()
		{
			$configColumList = array_keys($this->listConfig);
			$tableColumnList = array('document_srl', 'module_srl', 'category_srl', 'lang_code', 'is_notice',
					'title', 'title_bold', 'title_color', 'content', 'readed_count', 'voted_count', 
					'blamed_count', 'comment_count', 'trackback_count', 'uploaded_count', 'password', 'user_id',
					'user_name', 'nick_name', 'member_srl', 'email_address', 'homepage', 'tags', 'extra_vars',
					'regdate', 'last_update', 'last_updater', 'ipaddress', 'list_order', 'update_order',
					'allow_trackback', 'notify_message', 'status', 'comment_status');
					//,
					//'project_duration','project_type','budget','skill_required','project_status','bid_count',
					//'bid_average','started','ends','is_featured','is_deleted');
			$this->columnList = array_intersect($configColumList, $tableColumnList);

			if(in_array('summary', $configColumList)) array_push($this->columnList, 'content');

			// default column list add
			$defaultColumn = array('document_srl', 'module_srl', 'category_srl', 'lang_code', 'member_srl', 'last_update', 'comment_count', 'trackback_count', 'uploaded_count', 'status', 'regdate', 'title_bold', 'title_color');
			//$defaultColumn = array('title','bid_count','bid_average','skill_required','started','ends');
			
			//TODO guestbook, blog style supports legacy codes. 
			if($this->module_info->skin == 'xe_guestbook' || $this->module_info->default_style == 'blog')
			{
				$defaultColumn = $tableColumnList;
			}

			if (in_array('last_post', $configColumList)){
				array_push($this->columnList, 'last_updater');
			}

			// add is_notice
			if ($this->except_notice) {
				array_push($this->columnList, 'is_notice');
			}
			$this->columnList = array_unique(array_merge($this->columnList, $defaultColumn));

			// add table name
			foreach($this->columnList as $no => $value)
			{
				$this->columnList[$no] = 'documents.' . $value;
			}

		}

        /**
         * @brief display tag list
         **/
        function dispPaTagList() {
            // check if there is not grant fot view list, then alert an warning message
            if(!$this->grant->list) return $this->dispPaMessage('msg_not_permitted');

            // generate the tag module model object
            $oTagModel = &getModel('tag');

            $obj->mid = $this->module_info->mid;
            $obj->list_count = 10000;
            $output = $oTagModel->getTagList($obj);

            // automatically order
            if(count($output->data)) {
                $numbers = array_keys($output->data);
                shuffle($numbers);

                if(count($output->data)) {
                    foreach($numbers as $k => $v) {
                        $tag_list[] = $output->data[$v];
                    }
                }
            }

            Context::set('tag_list', $tag_list);

			$oSecurity = new Security();
			$oSecurity->encodeHTML('tag_list.');

            $this->setTemplateFile('tag_list');
        }

        /**
         * @brief display document write form
         **/
        function dispPaWrite() {

            // 1. 권한 체크.
            // 2. 카테고리 정보 셋팅.
            // 3. 수정인지 새로입력인지에 따라 또한 이전 저장된 글이 있는가에 따라
            // 권한을 조사도 하고 포인트도 조사하여 적절한 정보 셋팅 또는 뷰를 리턴.
            // 4. _statusNameList 함수를 이용해 상태 리스트 정보 셋팅.
            // 5. 저장된 문서가 없다면
            // 6. insert_document.xml 필터 header 에 추가.
            // 7. encodeHTML 통한 처리.


            // check grant, 쓰기 권한 체크. grant는 info/module.xml 에서 설정 가능한 권한값 정보이다.
            // 기타 permission, permission_level의 개념이 있는데 이는 module.xml 에서 설정하면 정해지는 것이다.
            if(!$this->grant->write_document) return $this->dispPaMessage('msg_not_permitted');

            $oDocumentModel = &getModel('document');

            // check if the category option is enabled or not, 카테고리 사용여부 및 권한 체크하여 정보 셋팅.
            if($this->module_info->use_category=='Y') {
                // get the user group information, 로그인 된 상태이면
                if(Context::get('is_logged')) {
                    $logged_info = Context::get('logged_info');
                    // 로그인한 회원의 group_list 정보는 키 값에 group_srl이 있고 값에 그룹 이름과 같은 데이터가 있다.
                    $group_srls = array_keys($logged_info->group_list);
                } else {
                    $group_srls = array();
                }
                $group_srls_count = count($group_srls);

                // check the grant after obtained the category list
                $normal_category_list = $oDocumentModel->getCategoryList($this->module_srl);
                if(count($normal_category_list)) {
                    foreach($normal_category_list as $category_srl => $category) {
                        $is_granted = true;
                        if($category->group_srls) {
                            $category_group_srls = explode(',',$category->group_srls);
                            $is_granted = false;
                            // 회원이 속한 그룹이 카테고리에 대한 권한을 가진 그룹에 하나라도 있다면,
                            if(count(array_intersect($group_srls, $category_group_srls))) $is_granted = true;
                        }
                        // 카테고리 리스트에 추가.
                        if($is_granted) $category_list[$category_srl] = $category;
                    }
                }
                Context::set('category_list', $category_list);
            }

            // GET parameter document_srl from request, document_srl 이 있다면 수정이다.
            $document_srl = Context::get('document_srl');
            // getDocument 에 0을 넣으면 그냥 빈 documentItem 객체를 반환.
            // $this->grant->manager 은 이 모듈 객체의 관리권한이며 TRUE, FALSE 이다. 아래에는 의미가 없는데 그냥 넣은듯.
            $oDocument = $oDocumentModel->getDocument(0, $this->grant->manager);
            // setDocument 로 빈 documentItem 객체에 셋팅. 기본은 extra_vars 까지 가져온다. 두번째 인자가 false이면 extra_vars 는 안가져옴.
            $oDocument->setDocument($document_srl); // 기본 $oDocument 생성 후 document_srl에 해당하는 문서 셋팅. 만약 문서가 존재하지 않으면 셋팅하지 않고 오류 출력.

            // 참고로 document.item.php 의 144 라인에서
            // if($this->get('member_srl') && ($this->get('member_srl') == $logged_info->member_srl || $this->get('member_srl')*-1 == $logged_info->member_srl)) return true;
            // -1을 곱하는 이유는 익명 게시판으로 할 경우 member_srl 에 로그인한 사용자의 member_srl에 -1을 곱하여 저장하기 때문.
            // 아래 module_srl 과 member_srl을 비교하여 같다면 $savedDoc = true 즉, 임시저장 문서라고 하는 이유는
            // 임시저장 문서를 저장하고 구분하는 방식이 어느 모듈에도 속해있지 않고 member_srl 의 회원의 문서임을 알아야하기 때문에
            // 임시저장 문서의 경우 module_srl 에 member_srl을 넣는다. xe 는 전역 sequence 개념을 사용하므로 module_srl과 member_srl 이 겹칠 일은 없다.
			if($oDocument->get('module_srl') == $oDocument->get('member_srl')) $savedDoc = true;
            $oDocument->add('module_srl', $this->module_srl); // 임시저장 문서일 경우 현재 모듈의 module_srl을 셋팅.


			// if the document is not granted, then back to the password input form
			// 문서에 권한이 없으면, 비밀번호 입력 폼으로 돌아간다.
            $oModuleModel = &getModel('module');
            // 문서가 존재하면 수정인데, 수정에서 권한이 없으면 비번 입력 뷰를 보여줌.
            if($oDocument->isExists()&&!$oDocument->isGranted()) return $this->setTemplateFile('input_password_form');
            // 문서가 존재하지 않으면 새로작성이므로,
            if(!$oDocument->isExists()) {
                // 이 모듈에 대한 포인트의 설정을 가져온다.
                // getModulePartConfig 의 경우 단지 해당 모듈의 기본이 아닌 생성된 모듈 객체의 설정값을 가져오는 것이 아니라
                // 첫번째 인자에 지정된 모듈에 대한 두번째 인자에 지정된 모듈시리얼을 가진 객체의 설정값을 가져오는 것도 의미한다.
                // 아래의 경우 point 라는 모듈에 대한 현재모듈인 bwswork모듈객체에 대한 설정을 가져온다.
                // 즉, 포인트 관련 이 객체의 설정값. bwswork의 경우 글쓰기할 때 -200 포인트 차감.
                // 다른 예를 하나 더 들면 코멘트 관련 이 객체의 설정값 이란 것도 있을 수 있다. 댓글수,추천,비추천,validation
                // 보통은 모듈 설정의 추가 설정 부분에 있다.

                $point_config = $oModuleModel->getModulePartConfig('point',$this->module_srl);
                $logged_info = Context::get('logged_info');
                $oPointModel = &getModel('point');
                // 이 모듈에 대한 포인트의 설정에서 insert_document 의 설정값을 가져온다.
                $pointForInsert = $point_config["insert_document"]; // 글 입력시 일단은 -200 포인트이다.
                if($pointForInsert < 0) { // - 이면 포인트 감소이므로
                    // 로그인 하지 않았다면 글 등록 불가. (포인트 부족)
                    if( !$logged_info ) return $this->dispPaMessage('msg_not_permitted');
                    // 로그인한 사용자의 포인트가 글 등록에 필요한 포인트보다 작으면 포인트 부족으로 글 등록 화면을 보여주지 못함.
                    else if (($oPointModel->getPoint($logged_info->member_srl) + $pointForInsert )< 0 ) return $this->dispPaMessage('msg_not_enough_point');
                }
            }

            // 기존에 문서가 없을 경우(새로 등록일 경우) 그 문서의 status 를 기본값으로 한다. PUBLIC
			if(!$oDocument->get('status')) $oDocument->add('status', $oDocumentModel->getDefaultStatus());


            // 공개,비밀과 같은 선택할 수 있는 상태의 리스트를 불러옴.
			$statusList = $this->_getStatusNameList($oDocumentModel);
			if(count($statusList) > 0) Context::set('status_list', $statusList);
			// get Document status config value
            Context::set('document_srl',$document_srl);
            Context::set('oDocument', $oDocument);


            // 도큐먼트 컨트롤러의 addXmlJsFilter 에는 module_srl이 들어가네?
            // 아래의 경우 Context::addJsFilter 로 사용하는데... 경로, 필터xml 이름 형태인데...
            // DocumentController 의 addXmlJsFilter 는 그 모듈의 document와 관계된 document_extra_keys type, required and others
            // 들에 대한 필터를 헤더에 추가. Context::addJsFilter 의 경우에는 직접적으로 특정 xml 형식의 필터를 추가하는 것.
            // apply xml_js_filter on header
            $oDocumentController = &getController('document');
            $oDocumentController->addXmlJsFilter($this->module_info->module_srl);



                        // document의 경우 확장변수 테이블이 extra_keys, extra_vars 가 있음. 이는 형식+값의 이중구조를 표현.
            // 가장 깊이가 깊은 변수형태임.
            // 모듈의 경우 module_extra_vars 테이블 하나로 표현. 1차.
            // member 의 경우 member 테이블에 extra_vars 라는 필드에 들어감. 0차.
            // if the document exists, then setup extra variabels on context
            // 문서가 존재하면서 임시저장된 글이 아니라면(임시저장된 글도 문서는 존재) 즉, 수정이라면 기존 확장변수 값들 셋팅.
            if($oDocument->isExists() && !$savedDoc) Context::set('extra_keys', $oDocument->getExtraVars());


            // document_srl(공통), module_srl(공통), category_srl(공통), member_srl(공통),
            // title(공통), content(공통), project_duration, project_type, budget, skill_required, project_status,
            // bid_count(자동), bid_average(자동), started, ends, is_featured, is_deleted(삭제로직)
            $oPaModel = &getModel('pa');
            $project = $oPaModel->getProject($oDocument->document_srl);
            // 추가할 변수들 각각이 정보이므로 각각에 대해서 셋팅해준다.
            // document의 확장변수와 같이 한꺼번에 합칠 필요가 없다.
            // $project_input_columns = array('project_duration','project_type','budget','skill_required','project_status','ends','is_featured');
            // write 폼에 뿌려줄 필드들의 경우 직접 html 태그로 넣어준다.


            // OPEN, WORKING, COMPLETE, CANCEL, EXPIRE 중 OPEN 이 아닌 것들은 수정을 못함.
            if($oDocument->isExists()&&$project->document_srl&&($project->project_status!='OPEN'&&$project->project_status!='WORKING')) return $this->dispPaMessage('msg_not_project_open_working');
            
            // * document의 title 과 같이 project와 공통으로 가지고 있는 항목의 데이터가 서로 다를 경우에도 생각을 해봐야함.
            // document 나 project 둘 중 하나라도 존재하지 않으면 프로젝트는 없는 것이다.
            // document 는 존재하는데 project는 존재하지 않을 수 있다. talk 이나 buysell 의 게시물들.
            // 하지만 project는 존재하는데 document가 없는 경우는 없다.
            // 그러나 프로젝트 관련 document가 휴지통으로 들어가 있다면 이럴 수 있다.
            if( $project->document_srl && $oDocument->isExists() ) {
                // 이미 등록된 프로젝트(게시글)이 있을 경우 값 셋팅.
                Context::set('project', $project);
            }
            else if($oDocument->isExists() && !$project->document_srl) {
                // 문서는 있으나 project table 에 데이터가 없을 경우, 이럴 경우는 거의 없으나 다른 문서가 이 모듈로 타고 올 수도 있으므로
                //if($oDocument->variables['module_srl']==$this->module_srl) { <-- 이건 안됨. document_srl을 기준으로 우선적으로 module_srl을 구함.
                if($oModuleModel->getModuleInfoByDocumentSrl($oDocument->document_srl)->module=='pa') {
                    // 수정 로직 수행. 단지 프로젝트 데이터만 없는 것임.
                }
                else {
                    // 다른 모듈의 문서임. 그 문서로 이동. 수정으로 바로 가면 좋겠지만 act가 dispBwstalkWrite가 될지 dispBwsbuysellWrite가 될지 모름.
                    return $this->setRedirectUrl(getUrl('','module_srl',$oDocument->module_srl,'document_srl',$oDocument->document_srl));
                }
            }
            else if($project->document_srl && !$oDocument->isExists()) {
                // project table 에 데이터는 있으나 문서는 없는 경우, 휴지통 또는 오류.
                alertMessage('프로젝트가 임시 삭제된 상태입니다. 관리자에게 문의해 주세요.');
                return;
            }
            else {
                // 이도 저도 아닐 경우 새로운 글 등록. 둘 다 없을 때...
            }


            // add JS filters
            Context::addJsFilter($this->module_path.'tpl/filter', 'insert.xml');

            // & 를 &amp; 와 같이 HTML 상에서 &로 보여질 수 있도록 htmlspecialchars 함수를 적용.
			$oSecurity = new Security();
			$oSecurity->encodeHTML('category_list.text', 'category_list.title');

            $this->setTemplateFile('write_form');
        }

		function _getStatusNameList(&$oDocumentModel)
		{
			$resultList = array();
			if(!empty($this->module_info->use_status))
			{
				$statusNameList = $oDocumentModel->getStatusNameList();
				$statusList = explode('|@|', $this->module_info->use_status);

				if(is_array($statusList))
				{
					foreach($statusList AS $key=>$value)
					{
						$resultList[$value] = $statusNameList[$value];
					}
				}
			}
			return $resultList;
		}

        /**
         * @brief display pa module deletion form
         **/
        function dispPaDelete() {
            // check grant
            if(!$this->grant->write_document) return $this->dispPaMessage('msg_not_permitted');

            // get the document_srl from request
            $document_srl = Context::get('document_srl');

            // if document exists, get the document information
            if($document_srl) {
                $oDocumentModel = &getModel('document');
                $oDocument = $oDocumentModel->getDocument($document_srl);
            }

            // if the document is not existed, then back to the pa content page
            if(!$oDocument->isExists()) return $this->dispPaContent();

			// if the document is not granted, then back to the password input form
            if(!$oDocument->isGranted()) return $this->setTemplateFile('input_password_form');

            Context::set('oDocument',$oDocument);

            /**
             * add JS filters
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'delete_document.xml');

            $this->setTemplateFile('delete_form');
        }

        /**
         * @brief display comment wirte form
         **/
        function dispPaWriteComment() {
            $document_srl = Context::get('document_srl');

            // check grant
            if(!$this->grant->write_comment) return $this->dispPaMessage('msg_not_permitted');

            // get the document information
            $oDocumentModel = &getModel('document');
            $oDocument = $oDocumentModel->getDocument($document_srl);
            if(!$oDocument->isExists()) return $this->dispPaMessage('msg_invalid_request');

			// Check allow comment
			if(!$oDocument->allowComment())
			{
				return $this->dispPaMessage('msg_not_allow_comment');
			}

            // obtain the comment (create an empty comment document for comment_form usage)
            $oCommentModel = &getModel('comment');
            $oSourceComment = $oComment = $oCommentModel->getComment(0);
            $oComment->add('document_srl', $document_srl);
            $oComment->add('module_srl', $this->module_srl);

            // setup document variables on context
            Context::set('oDocument',$oDocument);
            Context::set('oSourceComment',$oSourceComment);
            Context::set('oComment',$oComment);

            /**
             * add JS filter
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');

            $this->setTemplateFile('comment_form');
        }

        /**
         * @brief display comment replies page
         **/
        function dispPaReplyComment() {
            // check grant
            if(!$this->grant->write_comment) return $this->dispPaMessage('msg_not_permitted');

            // get the parent comment ID
            $parent_srl = Context::get('comment_srl');

            // if the parent comment is not existed
            if(!$parent_srl) return new Object(-1, 'msg_invalid_request');

            // get the comment
            $oCommentModel = &getModel('comment');
            $oSourceComment = $oCommentModel->getComment($parent_srl, $this->grant->manager);

            // if the comment is not existed, opoup an error message
            if(!$oSourceComment->isExists()) return $this->dispPaMessage('msg_invalid_request');
            if(Context::get('document_srl') && $oSourceComment->get('document_srl') != Context::get('document_srl')) return $this->dispPaMessage('msg_invalid_request');

			// Check allow comment
			$oDocumentModel = getModel('document');
			$oDocument = $oDocumentModel->getDocument($oSourceComment->get('document_srl'));
			if(!$oDocument->allowComment())
			{
				return $this->dispPaMessage('msg_not_allow_comment');
			}

            // get the comment information
            $oComment = $oCommentModel->getComment();
            $oComment->add('parent_srl', $parent_srl);
            $oComment->add('document_srl', $oSourceComment->get('document_srl'));

            // setup comment variables
            Context::set('oSourceComment',$oSourceComment);
            Context::set('oComment',$oComment);
            Context::set('module_srl',$this->module_info->module_srl);

            /**
             * add JS filters
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');

            $this->setTemplateFile('comment_form');
        }

        /**
         * @brief display the comment modification from
         **/
        function dispPaModifyComment() {
            // check grant
            if(!$this->grant->write_comment) return $this->dispPaMessage('msg_not_permitted');

            // get the document_srl and comment_srl
            $document_srl = Context::get('document_srl');
            $comment_srl = Context::get('comment_srl');

            // if the comment is not existed
            if(!$comment_srl) return new Object(-1, 'msg_invalid_request');

            // get comment information
            $oCommentModel = &getModel('comment');
            $oComment = $oCommentModel->getComment($comment_srl, $this->grant->manager);

            // if the comment is not exited, alert an error message
            if(!$oComment->isExists()) return $this->dispPaMessage('msg_invalid_request');

			// if the comment is not granted, then back to the password input form
            if(!$oComment->isGranted()) return $this->setTemplateFile('input_password_form');

            // setup the comment variables on context
            Context::set('oSourceComment', $oCommentModel->getComment());
            Context::set('oComment', $oComment);

            /**
             * add JS fitlers
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');

            $this->setTemplateFile('comment_form');
        }

        /**
         * @brief display the delete comment  form
         **/
        function dispPaDeleteComment() {
            // check grant
            if(!$this->grant->write_comment) return $this->dispPaMessage('msg_not_permitted');

            // get the comment_srl to be deleted
            $comment_srl = Context::get('comment_srl');

            // if the comment exists, then get the comment information
            if($comment_srl) {
                $oCommentModel = &getModel('comment');
                $oComment = $oCommentModel->getComment($comment_srl, $this->grant->manager);
            }

            // if the comment is not existed, then back to the pa content page
            if(!$oComment->isExists() ) return $this->dispPaContent();

            // if the comment is not granted, then back to the password input form
            if(!$oComment->isGranted()) return $this->setTemplateFile('input_password_form');

            Context::set('oComment',$oComment);

            /**
             * add JS filters
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'delete_comment.xml');

            $this->setTemplateFile('delete_comment_form');
        }

        /**
         * @brief display the delete trackback form
         **/
        function dispPaDeleteTrackback() {
            // get the trackback_srl
            $trackback_srl = Context::get('trackback_srl');

            // get the trackback data
            $oTrackbackModel = &getModel('trackback');
			$columnList = array('trackback_srl');
            $output = $oTrackbackModel->getTrackback($trackback_srl, $columnList);
            $trackback = $output->data;

            // if no trackback, then display the pa content
            if(!$trackback) return $this->dispPaContent();

            //Context::set('trackback',$trackback);	//perhaps trackback variables not use in UI

            /**
             * add JS filters
             **/
            Context::addJsFilter($this->module_path.'tpl/filter', 'delete_trackback.xml');

            $this->setTemplateFile('delete_trackback_form');
        }

        /**
         * @brief display pa message
         **/
        function dispPaMessage($msg_code) {
            $msg = Context::getLang($msg_code);
            if(!$msg) $msg = $msg_code;
            Context::set('message', $msg);
            $this->setTemplateFile('message');
        }

        /**
         * @brief the method for displaying the warning messages
         * display an error message if it has not  a special design
         **/
        function alertMessage($message) {
            $script =  sprintf('<script> jQuery(function(){ alert("%s"); } );</script>', Context::getLang($message));
            Context::addHtmlFooter( $script );
        }


         /**
         * @brief 추가 로직
         * 마감 하루 전 프로젝트
         **/
        function dispPaExpiredSoon() {
            $oPaModel = &getModel('pa');
            //function getProjectList($module_srl, $page=1, $list_count=20, $project_status='OPEN', $sort_index='document_srl', $order_type='desc') {
            //function getProjectListByEnddate($module_srl, $days=1, $list_count=20, $project_status='OPEN', $sort_index='document_srl', $order_type='desc') {
            $output = $oPaModel->getProjectListByEnddate($this->module_srl);
            Context::set('projects', $output->data);
            Context::set('total_count', $output->total_count);
            Context::set('total_page', $output->total_page);
            Context::set('page', $output->page);
            Context::set('page_navigation', $output->page_navigation);
            
            $this->setTemplateFile('pa_expired_soon_list');
        }

        /**
         * @brief 추가 로직
         * 입찰수 낮은 순
         **/
        function dispPaOrderbyLowbid() {
            $oPaModel = &getModel('pa');

            //function getProjectList($module_srl, $page=1, $list_count=20, $project_status='OPEN', $sort_index='document_srl', $order_type='desc') {
            $output = $oPaModel->getProjectList($this->module_srl, Context::get('page'), 20, 'OPEN', 'bid_count', 'asc');
            Context::set('projects', $output->data);
            Context::set('total_count', $output->total_count);
            Context::set('total_page', $output->total_page);
            Context::set('page', $output->page);
            Context::set('page_navigation', $output->page_navigation);

            $this->setTemplateFile('pa_orderby_lowbid_list');
        }

        /**
         * @brief 추가 로직
         * 풀타임 프로젝트
         **/
        function dispPaFulltimeProject() {
            $oPaModel = &getModel('pa');

            //function getProjectListByProjectType($module_srl, $page=1, $list_count=20, $project_type='fulltime', $project_status='OPEN', $sort_index='document_srl', $order_type='desc') {
            $output = $oPaModel->getProjectListByProjectType($this->module_srl, Context::get('page'), 20, 'fulltime');
            Context::set('projects', $output->data);
            Context::set('total_count', $output->total_count);
            Context::set('total_page', $output->total_page);
            Context::set('page', $output->page);
            Context::set('page_navigation', $output->page_navigation);

            $this->setTemplateFile('pa_fulltime_project_list');
        }

        /**
         * @brief 추가 로직
         * 단기 프로젝트
         **/
        function dispPaFixedProject() {
            $oPaModel = &getModel('pa');

            //function getProjectListByProjectType($module_srl, $page=1, $list_count=20, $project_type='fulltime', $project_status='OPEN', $sort_index='document_srl', $order_type='desc') {
            $output = $oPaModel->getProjectListByProjectType($this->module_srl, Context::get('page'), 20, 'fixed');
            Context::set('projects', $output->data);
            Context::set('total_count', $output->total_count);
            Context::set('total_page', $output->total_page);
            Context::set('page', $output->page);
            Context::set('page_navigation', $output->page_navigation);

            $this->setTemplateFile('pa_fixed_project_list');
        }

        /**
         * @brief 추가 로직
         * 내가 등록한 프로젝트
         **/
        function dispPaRegisteredbymeProjectList() {
            $oPaModel = &getModel('pa');
            $logged_info = Context::get('logged_info');
            $is_logged = Context::get('is_logged');
            if(!$is_logged) return $this->dispPaMessage('msg_not_logged');
            //function getProjectListByMemberSrl($module_srl, $page=1, $list_count=20, $member_srl=NULL, $project_status='OPEN', $sort_index='document_srl', $order_type='desc') {
            $output = $oPaModel->getProjectListByMemberSrl($this->module_srl, Context::get('page'), 20, $logged_info->member_srl, 'OPEN,WORKING,COMPLETE,CANCEL,EXPIRE');
            Context::set('projects', $output->data);
            Context::set('total_count', $output->total_count);
            Context::set('total_page', $output->total_page);
            Context::set('page', $output->page);
            Context::set('page_navigation', $output->page_navigation);

            $this->setTemplateFile('pa_registeredbyme_project_list');
        }

        /**
         * @brief 추가 로직
         * 내 입찰 리스트
         **/
        function dispPaRegisteredbymeBidList() {
            $oPaModel = &getModel('pa');
            $logged_info = Context::get('logged_info');
            $is_logged = Context::get('is_logged');
            if(!$is_logged) return $this->dispPaMessage('msg_not_logged');
            //function getBidListByMemberSrl($module_srl, $member_srl, $page='1', $list_count='20', $sort_index='list_order', $order_type='desc')
            $output = $oPaModel->getBidListByMemberSrl($this->module_srl, $logged_info->member_srl, Context::get('page'));
            Context::set('bids', $output->data);
            Context::set('total_count', $output->total_count);
            Context::set('total_page', $output->total_page);
            Context::set('page', $output->page);
            Context::set('page_navigation', $output->page_navigation);

            $this->setTemplateFile('pa_registeredbyme_bid_list');
        }
    }
?>
