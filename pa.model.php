<?php
    /**
     * @class  paModel
     * @author kimdongmin (kdm3843@gmail.com)
     * @brief  pa module  Model class
     **/

    class paModel extends module {
        /**
         * @brief initialization
         **/
        function init() {
        }

        /**
         * @brief get the list configuration
         * 특정 모듈의 관리자의 목록설정 단에 설정된 리스트를 구하는 것.
         **/
        function getListConfig($module_srl) {
            $oModuleModel = &getModel('module');
            $oDocumentModel = &getModel('document');

            // get the list config value, if it is not exitsted then setup the default value
            $list_config = $oModuleModel->getModulePartConfig('pa', $module_srl);
            if(!$list_config || !count($list_config)) $list_config = array('no','title','bid_count','bid_average','skill_required','started','ends','project_status');

            // get the extra variables
            $inserted_extra_vars = $oDocumentModel->getExtraKeys($module_srl);

            foreach($list_config as $key) {
                if(preg_match('/^([0-9]+)$/',$key))
				{
					if($inserted_extra_vars[$key])
					{
						$output['extra_vars'.$key] = $inserted_extra_vars[$key];
					}
					else
					{
						continue;
					}
				}
                else $output[$key] = new ExtraItem($module_srl, -1, Context::getLang($key), $key, 'N', 'N', 'N', null);
            }
            return $output;
        }

        /** 
         * @brief return the default list configration value
         * 관리자 단에서 추가할 리스트 목록에 표시될 모든 필드의 값들을 구하는 것.
         **/
        function getDefaultListConfig($module_srl) {
            // add virtual srl, title, registered date, update date, nickname, ID, name, readed count, voted count etc.
            $virtual_vars = array( 'no', 'title', 'regdate', 'last_update', 'last_post', 'nick_name',
					'user_id', 'user_name', 'readed_count', 'voted_count', 'blamed_count', 'thumbnail', 'summary',
            		'project_duration','project_type','budget','skill_required','project_status','bid_count','bid_average','started','ends','project_status');
            foreach($virtual_vars as $key) {
                $extra_vars[$key] = new ExtraItem($module_srl, -1, Context::getLang($key), $key, 'N', 'N', 'N', null);
            }

            // get the extra variables from the document model
            $oDocumentModel = &getModel('document');
            $inserted_extra_vars = $oDocumentModel->getExtraKeys($module_srl);

            if(count($inserted_extra_vars)) foreach($inserted_extra_vars as $obj) $extra_vars['extra_vars'.$obj->idx] = $obj;

            return $extra_vars;

        }

        /**
         * @brief return module name in sitemap
         **/
		function triggerModuleListInSitemap(&$obj)
		{
			array_push($obj, 'pa');
		}



        /**
         * @brief return project 정보
         */
        function getProject($document_srl)
        {
            $args = new stdClass();
            $args->document_srl = $document_srl;
            $output = executeQuery('pa.getProject',$args);
            // $output->toBool() 이 false 이더라도
            // $output->data 는 null이고 데이터가 없어도 $output->data 는 null 이라서
            // 무조건 한개 결과값 또는 null이 반환됨.
            return $output->data;

            // $output 의 경우 object 를 상속 받은 클래스의 객체이다.
            // $output->toBool() 의 경우 object 필드에
            // $error 가 0 이라면 true 를 $error 가 -1 이라면 false 를 리턴한다.
            // 로직에서 정의하기 나름이겠지만 보통은 에러가 나면 false 이다.
            // 만약 에러가 아니라면 $output->data 에 결과가 저장되어 있다.
            // $output->data는 mysql_fetch_object 를 했을 때와 비슷한 형식의 데이터이다.
            // excuteQueryArray 는 결과가 한개이더라도 배열로 리턴해준다.
        }

        /**
         * @brief return project 정보를 담은 output
         */
        function getProjectList($module_srl, $page=1, $list_count=20, $project_status='OPEN', $sort_index='document_srl', $order_type='desc') {
            $args = new stdClass();
            $args->module_srl = $module_srl;
            // 가능한 sort_index 목록. document_srl, bid_count, bid_average, started,
            $args->list_count = $list_count;
            $args->page = $page;
            $args->sort_index = $sort_index;
            $args->order_type = $order_type;
            $args->project_status = $project_status;
            $output = executeQueryArray('pa.getProjectList', $args);
//          $output->error=0
//          $output->message='success'
//          $output->variables = array('_query'=>'','_elapsed_time'=>'')
//          $output->httpStatusCode
//          $output->total_count
//          $output->total_page
//          $output->page
//          $output->data
//          $output->page_navigation
//            page_navigation =
//                PageHandler(
//                    total_count = 2
//                    total_page = 1
//                    cur_page = 1
//                    page_count = 1
//                    first_page = 1
//                    last_page = 1
//                    point = 0
//            )
            return $output;
        }

        // 마감 하루전 프로젝트들과 같이 마감일 기준 몇일 전까지의 프로젝트들 가져오기.
        function getProjectListByEnddate($module_srl, $days=1, $list_count=20, $project_status='OPEN', $sort_index='document_srl', $order_type='desc') {
            $args = new stdClass();
            $args->module_srl = $module_srl;
            // 오늘 날짜에 하루 더한 것 보다 작은 것들.
            $day_string = "+".$days." day";
            $args->days = date('YmdHis', strtotime($day_string));

            // 가능한 sort_index 목록. document_srl, bid_count, bid_average, started,
            $args->list_count = $list_count;
            $args->sort_index = $sort_index;
            $args->order_type = $order_type;
            $args->project_status = $project_status;

            $output = executeQueryArray('pa.getProjectListByEnddate', $args);
            return $output;
        }

        /**
         * @brief return project 정보를 담은 output
         */
        function getProjectListByProjectType($module_srl, $page=1, $list_count=20, $project_type='fulltime', $project_status='OPEN', $sort_index='document_srl', $order_type='desc') {
            $args = new stdClass();
            $args->module_srl = $module_srl;
            // 가능한 sort_index 목록. document_srl, bid_count, bid_average, started,
            $args->list_count = $list_count;
            $args->page = $page;
            $args->sort_index = $sort_index;
            $args->order_type = $order_type;
            $args->project_status = $project_status;
            $args->project_type = $project_type;
            $output = executeQueryArray('pa.getProjectListByProjectType', $args);

            return $output;
        }

        /**
         * @brief return project 정보를 담은 output
         */
        function getProjectListByMemberSrl($module_srl, $page=1, $list_count=20, $member_srl=NULL, $project_status='OPEN', $sort_index='document_srl', $order_type='desc') {
            $args = new stdClass();
            $args->module_srl = $module_srl;
            // 가능한 sort_index 목록. document_srl, bid_count, bid_average, started,
            $args->list_count = $list_count;
            $args->page = $page;
            $args->sort_index = $sort_index;
            $args->order_type = $order_type;
            $args->project_status = $project_status;
            $args->member_srl = $member_srl;
            $output = executeQueryArray('pa.getProjectListByMemberSrl', $args);

            return $output;
        }


        /**
         * @brief return bid list
         */
        function getBidList($document_srl)
        {
            $args = new stdClass();
            $args->document_srl = $document_srl;
            $output = executeQueryArray('pa.getBidList', $args);
            return $output->data;
        }

        /**
         * @brief return awarded bid list
         */
        function getBidListAwarded($document_srl)
        {
            $args = new stdClass();
            $args->document_srl = $document_srl;
            $output = executeQueryArray('pa.getBidListAwarded', $args);
            return $output->data;
        }

        /**
         * @brief return bid list by member_srl
         */
        function getBidListByMemberSrl($module_srl, $member_srl, $page='1', $list_count='20', $sort_index='list_order', $order_type='desc')
        {
            $args = new stdClass();
            $args->module_srl = $module_srl;
            $args->member_srl = $member_srl;
            $args->page = $page;
            $args->list_count = $list_count;
            $args->sort_index = $sort_index;
            $args->order_type = $order_type;
            $output = executeQueryArray('pa.getBidListByMemberSrl', $args);

            return $output;
        }

        /**
         * @brief return bid
         */
        function getBid($bid_srl)
        {
            $args = new stdClass();
            $args->bid_srl = $bid_srl;
            $output = executeQuery('pa.getBid', $args);
            return $output->data;
        }
    }
?>
