<?php
    /**
     * @class  paController
     * @author kimdongmin (kdm3843@gmail.com)
     * @brief  pa module Controller class
     **/

    class paController extends pa {

        /**
         * @brief initialization
         **/
        function init() {
        }

        /**
         * @brief insert document
         **/
        function procPaInsertDocument() {
            // check grant
			if($this->module_info->module != "pa") return new Object(-1, "msg_invalid_request");
            if(!$this->grant->write_document) return new Object(-1, 'msg_not_permitted');
            $logged_info = Context::get('logged_info');

            // setup variables
            $obj = Context::getRequestVars();
            $obj->module_srl = $this->module_srl;
            if($obj->is_notice!='Y'||!$this->grant->manager) $obj->is_notice = 'N';
			$obj->commentStatus = $obj->comment_status;

            settype($obj->title, "string");
            if($obj->title == '') $obj->title = cut_str(strip_tags($obj->content),20,'...');
            //setup document title to 'Untitled'
            if($obj->title == '') $obj->title = 'Untitled';

            // unset document style if the user is not the document manager
            if(!$this->grant->manager) {
                unset($obj->title_color);
                unset($obj->title_bold);
            }

            // generate document module model object
            $oDocumentModel = &getModel('document');

            // generate document module의 controller object
            $oDocumentController = &getController('document');

            // check if the document is existed
            $oDocument = $oDocumentModel->getDocument($obj->document_srl, $this->grant->manager);

            // if use anonymous is true
            if($this->module_info->use_anonymous == 'Y') {
                $obj->notify_message = 'N';
                $this->module_info->admin_mail = '';
                $obj->member_srl = -1*$logged_info->member_srl;
                $obj->email_address = $obj->homepage = $obj->user_id = '';
                $obj->user_name = $obj->nick_name = 'anonymous';
                $bAnonymous = true;
				$oDocument->add('member_srl', $obj->member_srl);
            }
            else
            {
                $bAnonymous = false;
            }

            // 모델 불러오기.
            $oPaModel = &getModel('pa');

            // update the document if it is existed. 프로젝트 수정.
            if($oDocument->isExists() && $oDocument->document_srl == $obj->document_srl) {
				if(!$oDocument->isGranted()) return new Object(-1,'msg_not_permitted');

                // document_srl(공통), module_srl(공통), category_srl(공통), member_srl(공통),
                // title(공통), content(공통), project_duration, project_type, budget, skill_required, project_status,
                // bid_count(자동), bid_average(자동), started, ends, is_featured, is_deleted(삭제로직)
                // 수정 시에는 title, content, project_duration, skill_required 만 업데이트 된다.
                $project = $oPaModel->getProject($oDocument->document_srl);
                if(!$project->document_srl) { // project table 에 데이터가 없다면 등록해준다.

                    if($obj->is_featured=='Y') {
                        $output = $this->processFeaturedProject($logged_info->member_srl, &$obj);
                        if(!$output->toBool()) return $output;
                    }
                    $project->document_srl = $oDocument->document_srl;
                    $project->module_srl = $oDocument->variables['module_srl'];
                    $project->category_srl = $oDocument->variables['category_srl'];
                    $project->member_srl = $oDocument->variables['member_srl'];
                    $project->project_type = $obj->project_type;
                    $project->budget = $obj->budget;
                    $project->project_status = 'OPEN';
                    $project->bid_count = 0;
                    $project->bid_average = 0;
                    $now_unixtime = mktime();
                    $project->started = date( 'YmdHis', $now_unixtime );
                    $project->ends = date( 'YmdHis', $now_unixtime + 7*24*60*60 );
                    $project->is_featured = $obj->is_featured;
                    $project->is_deleted = 'N';

                    $project->title = $obj->title;
                    $project->content = $obj->content;
                    $project->project_duration = $obj->project_duration;
                    $project->skill_required = $obj->skill_required;

                    unset($output_query);
                    $output_query = executeQuery('pa.insertProject', $project);
                    if(!$output_query->toBool()) return $output_query;
                }
                else { // 기존 프로젝트가 있다면 (당연하겠지만) 필요 정보 수정.
                    $project->title = $obj->title;
                    $project->content = $obj->content;
                    $project->project_duration = $obj->project_duration;
                    $project->skill_required = $obj->skill_required;
                    unset($output_query);
                    $output_query = executeQuery('pa.updateProject', $project);
                    if(!$output_query->toBool()) return $output_query;
                }

                // output 에는 document_srl 정보랑 error, variables에 담긴 db query 정보 정도 밖에 없다.
                $output = $oDocumentController->updateDocument($oDocument, $obj);

                $msg_code = 'success_updated';

            // insert a new document otherwise. 새 프로젝트 등록.
            } else {

                // output 에는 document_srl 정보랑 error, variables에 담긴 db query 정보 정도 밖에 없다.
                $output = $oDocumentController->insertDocument($obj, $bAnonymous);
                $obj->document_srl = $output->get('document_srl');

                // document_srl(공통), module_srl(공통), category_srl(공통), member_srl(공통),
                // title(공통), content(공통), project_duration, project_type, budget, skill_required, project_status,
                // bid_count(자동), bid_average(자동), started, ends, is_featured, is_deleted(삭제로직)
                if($obj->is_featured=='Y') {
                    $output = $this->processFeaturedProject($logged_info->member_srl, &$obj);
                    if(!$output->toBool()) return $output;
                }
                $project = new stdClass();
                $project->document_srl = $obj->document_srl;
                $project->module_srl = $obj->module_srl;
                $project->category_srl = $obj->category_srl;
                $project->member_srl = $obj->member_srl;
                $project->project_type = $obj->project_type;
                $project->budget = $obj->budget;
                $project->project_status = 'OPEN';
                $project->bid_count = 0;
                $project->bid_average = 0;
                $now_unixtime = mktime();
                $project->started = date( 'YmdHis', $now_unixtime ); // strftime( 'YmdHis', $now_unixtime ); <-- 이건 왜 안되지?
                $project->ends = date( 'YmdHis', $now_unixtime + 7*24*60*60 ); // php에서는 unixtime 은 s 이다. javascript 는 ms.
                $project->is_deleted = 'N';
                $project->title = $obj->title;
                $project->content = $obj->content;
                $project->project_duration = $obj->project_duration;
                $project->skill_required = $obj->skill_required;

                unset($output_query);
                $output_query = executeQuery('pa.insertProject', $project);
                if(!$output_query->toBool()) return $output_query;

                $msg_code = 'success_registed';

                // send an email to admin user
                if($output->toBool() && $this->module_info->admin_mail) {
                    $oMail = new Mail();
                    $oMail->setTitle($obj->title);
                    $oMail->setContent( sprintf("From : <a href=\"%s\">%s</a><br/>\r\n%s", getFullUrl('','document_srl',$obj->document_srl), getFullUrl('','document_srl',$obj->document_srl), $obj->content));
                    $oMail->setSender($obj->user_name, $obj->email_address);

                    $target_mail = explode(',',$this->module_info->admin_mail);
                    for($i=0;$i<count($target_mail);$i++) {
                        $email_address = trim($target_mail[$i]);
                        if(!$email_address) continue;
                        $oMail->setReceiptor($email_address, $email_address);
                        $oMail->send();
                    }
                }

            }

            // if there is an error
            if(!$output->toBool()) return $output;

            // return the results
            $this->add('mid', Context::get('mid'));
            $this->add('document_srl', $obj->document_srl);

            // alert a message
            $this->setMessage($msg_code);
        }

        function processFeaturedProject($member_srl, &$obj) {
            $obj->is_notice = 'Y';
            $output = executeQuery('pa.updateDocumentNotice', $obj);
            if(!$output->toBool()) return $output;

            // --------------------------- 추천 프로젝트와 관련된 포인트 차감 로직 -------------------
            // 추천 프로젝트의 경우 포인트를 차감하기. 보유한 포인트가 프로젝트 등록에 필요한 포인트+1000포인트 보다 커야함.
            $oModuleModel = &getModel('module');
            $config = $oModuleModel->getModuleConfig('point');
            $module_config = $oModuleModel->getModulePartConfig('point',$this->module_srl);
            // Get the points of the member
            $oPointModel = &getModel('point');
            $oPointController = &getController('point');
            $cur_point = $oPointModel->getPoint($member_srl, true);
            $insert_point = $module_config['insert_document'];
            if(strlen($insert_point) == 0 && !is_int($insert_point)) $insert_point = $config->insert_document;
            // ----- 추천 프로젝트로 등록이 Y일 경우 회원의 포인트가 모자라면 포인트가 모자라다는 메시지와 함께 리턴.
            if( $cur_point - (1000-$insert_point) < 0 ) {
                return new Object(-1, 'msg_not_enough_point');
            }
            $cur_point -= 1000;
            $oPointController->setPoint($member_srl, $cur_point);
            return new Object();
            // --------------------------- 추천 프로젝트와 관련된 포인트 차감 로직 끝. -------------------
        }

        /**
         * @brief delete the document
         **/
        function procPaDeleteDocument() {
            // get the document_srl
            $document_srl = Context::get('document_srl');

            // if the document is not existed
            if(!$document_srl) return $this->doError('msg_invalid_document');

            // generate document module controller object 
            $oDocumentController = &getController('document');

            // delete the document
            $output = $oDocumentController->deleteDocument($document_srl, $this->grant->manager);
            if(!$output->toBool()) return $output;

            // 프로젝트 테이블의 데이터 삭제.
            $args->document_srl = $document_srl;
            $output = executeQuery('pa.deleteProject', $args);
            if(!$output->toBool()) return $output;

            // alert an message
            $this->add('mid', Context::get('mid'));
            $this->add('page', $output->get('page'));
            $this->setMessage('success_deleted');
        }

        /**
         * @brief vote
         **/
        function procPaVoteDocument() {
            // generate document module controller object
            $oDocumentController = &getController('document');

            $document_srl = Context::get('document_srl');
            return $oDocumentController->updateVotedCount($document_srl);
        }


        /**
         * @brief insert bid
         **/
        function procPaInsertBid() {
            // check grant
            //if(!$this->grant->write_comment) return new Object(-1, 'msg_not_permitted');
            $is_logged = Context::get('is_logged');
            $logged_info = Context::get('logged_info');

            if(!$is_logged) return new Object(-1, 'msg_not_permitted');

            // get the relevant data for inserting comment
            $obj = Context::gets('document_srl','bid_srl','message','bid_price','currency');
            $obj->module_srl = $this->module_srl;

            $obj->member_srl = $logged_info->member_srl;

            // check if the document is existed
            $oDocumentModel = &getModel('document');
            $oDocument = $oDocumentModel->getDocument($obj->document_srl);
            if(!$oDocument->isExists()) return new Object(-1,'msg_invalid_document');

            $oPaModel = &getModel('pa');

            // remove XE's own tags from the contents
            $obj->message = preg_replace('!<\!--(Before|After)(Document|Comment)\(([0-9]+),([0-9]+)\)-->!is', '', $obj->message);
            $obj->message = htmlspecialchars($obj->message);
            $obj->message = nl2br($obj->message);

            // bid_srl 에 해당하는 입찰이 존재하는지 확인. 존재하지 않는다면 새로 등록.
            if(!$obj->bid_srl) {
                $obj->bid_srl = getNextSequence();
                // determine the order
                $obj->list_order = getNextSequence() * -1;
            } else {
                $bid = $oPaModel->getBid($obj->bid_srl);
            }

            // bid_srl 에 해당하는 입찰이 없다면 새로 등록.
            if($bid->bid_srl != $obj->bid_srl) {

                // Get the points of the member
                $oPointModel = &getModel('point');
                $oPointController = &getController('point');
                $cur_point = $oPointModel->getPoint($logged_info->member_srl, true);


/* MYTODO: 입찰 관련 포인트 차감. 이벤트 종료 후 변경해야함.
                // 입찰에 필요한 회원의 포인트가 모자라면 포인트가 모자라다는 메시지와 함께 리턴.
                if( $cur_point - 500 < 0 ) {
                    return new Object(-1, 'msg_not_enough_point');
                }
*/
                $output = executeQuery('pa.insertBid',$obj);
                if(!$output->toBool()) return $output;
/*
                $cur_point -= 500;
                $oPointController->setPoint($logged_info->member_srl, $cur_point);
*/


                /*
                        // send an email
                        if($output->toBool() && $this->module_info->admin_mail) {
                            $oMail = new Mail();
                            $oMail->setTitle($oDocument->getTitleText());
                            $oMail->setContent( sprintf("From : <a href=\"%s#comment_%d\">%s#comment_%d</a><br/>\r\n%s", getFullUrl('','document_srl',$obj->document_srl),$obj->comment_srl, getFullUrl('','document_srl',$obj->document_srl), $obj->comment_srl, $obj->content));
                            $oMail->setSender($obj->user_name, $obj->email_address);

                            $target_mail = explode(',',$this->module_info->admin_mail);
                            for($i=0;$i<count($target_mail);$i++) {
                                $email_address = trim($target_mail[$i]);
                                if(!$email_address) continue;
                                $oMail->setReceiptor($email_address, $email_address);
                                $oMail->send();
                            }
                        }
                */

                // update the bid if it is existed
            } else {
                // check the grant
                //if(!$comment->isGranted()) return new Object(-1,'msg_not_permitted');

                //$output = executeQuery('pa.updateBid',$obj);
                //$bid_srl = $obj->bid_srl;
            }


            $output = $this->updateProjectBidInfo($oDocument->document_srl);
            if(!$output->toBool()) return $output; // 여기서 또 한번 오류 검증.

            $this->setMessage('success_registed');
            $this->add('mid', Context::get('mid'));
            $this->add('document_srl', $obj->document_srl);
            $this->add('bid_srl', $obj->bid_srl);
        }

        /**
         * @brief award bid
         **/
        function procPaAwardBid() {

            $is_logged = Context::get('is_logged');
            $logged_info = Context::get('logged_info');
            // 로그인하지 않았다면 권한이 없음.
            if(!$is_logged) return new Object(-1, 'msg_not_permitted');

            $bid_srl = (int)Context::get('bid_srl');
            if(!$bid_srl) $bid_srl = (int)Context::get('target_srl');
            if(!$bid_srl) return new Object(-1,'msg_invalid_request');

            $oPaModel = &getModel('pa');
            $bid = $oPaModel->getBid($bid_srl);
            // bid_srl 에 해당하는 입찰이 없다면 잘못된 요청임.
            if($bid_srl!=$bid->bid_srl) return new Object(-1, 'msg_invalid_request');
            // 프로젝트의 member_srl 과 현재 로그인한 회원의 member_srl 이 다르면 권한이 없음.
            $project = $oPaModel->getProject($bid->document_srl);
            if($logged_info->member_srl!=$project->member_srl && $logged_info->is_admin!='Y') return new Object(-1, 'msg_not_permitted');
            // 이미 채택되었다면
            if($bid->is_awarded=='Y') return new Object(-1, 'msg_already_awarded');

            $bid->is_awarded = 'Y';

            $output = executeQuery('pa.updateBid', $bid);
            if(!$output->toBool()) return $output;


            $this->setMessage('success_awarded');
            //$this->add('mid', Context::get('mid'));
            //$this->add('document_srl', Context::get('document_srl'));
            //$this->setMessage('success_awarded');
            
            //$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'mid', Context::get('mid'), 'document_srl', $bid->document_srl);
            //$this->setRedirectUrl($returnUrl);
        }

        /**
         * @brief revoke bid
         **/
        function procPaRevokeBid() {

            $is_logged = Context::get('is_logged');
            $logged_info = Context::get('logged_info');
            // 로그인하지 않았다면 권한이 없음.
            if(!$is_logged) return new Object(-1, 'msg_not_permitted');

            
            $bid_srl = (int)Context::get('bid_srl');
            if(!$bid_srl) $bid_srl = (int)Context::get('target_srl');
            if(!$bid_srl) return new Object(-1,'msg_invalid_request');
            

            $oPaModel = &getModel('pa');
            $bid = $oPaModel->getBid($bid_srl);
            // bid_srl 에 해당하는 입찰이 없다면 잘못된 요청임.
            if($bid_srl!=$bid->bid_srl) return new Object(-1, 'msg_invalid_request');
            // 프로젝트의 member_srl 과 현재 로그인한 회원의 member_srl 이 다르면 권한이 없음.
            $project = $oPaModel->getProject($bid->document_srl);
            if($logged_info->member_srl!=$project->member_srl && $logged_info->is_admin!='Y') return new Object(-1, 'msg_not_permitted');
            // 이미 채택되었다면
            if($bid->is_awarded=='N') return new Object(-1, 'msg_already_revoked');

            $bid->is_awarded = 'N';

            $output = executeQuery('pa.updateBid', $bid);
            if(!$output->toBool()) return $output;


            $this->setMessage('success_revoked');
            
            //$this->add('mid', Context::get('mid'));
            //$this->add('document_srl', Context::get('document_srl'));
            //$this->setMessage('success_revoked');
            
            //$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'mid', Context::get('mid'), 'document_srl', $bid->document_srl);
            //$this->setRedirectUrl($returnUrl);
        }

        /**
         * @brief delete bid
         **/
        function procPaDeleteBid() {

            $is_logged = Context::get('is_logged');
            $logged_info = Context::get('logged_info');
            // 로그인하지 않았다면 권한이 없음.
            if(!$is_logged) return new Object(-1, 'msg_not_permitted');

            
            $bid_srl = (int)Context::get('bid_srl');
            if(!$bid_srl) $bid_srl = (int)Context::get('target_srl');
            if(!$bid_srl) return new Object(-1,'msg_invalid_request');
            

            $oPaModel = &getModel('pa');
            $bid = $oPaModel->getBid($bid_srl);
            // bid_srl 에 해당하는 입찰이 없다면 잘못된 요청임.
            if($bid_srl!=$bid->bid_srl) return new Object(-1, 'msg_invalid_request');
            // 입찰의 member_srl 과 현재 로그인한 회원의 member_srl 이 다르면 권한이 없음.
            if($logged_info->member_srl!=$bid->member_srl && $logged_info->is_admin!='Y') return new Object(-1, 'msg_not_permitted');

            $output = executeQuery('pa.deleteBid', $bid);
            if(!$output->toBool()) return $output;

            $output = $this->updateProjectBidInfo($bid->document_srl);
            if(!$output->toBool()) return $output;

  
            $this->setMessage('success_deleted');
            
            
            //$this->add('mid', Context::get('mid'));
            //$this->add('document_srl', Context::get('document_srl'));
            //$this->setMessage('success_deleted');
            
            //$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'mid', Context::get('mid'), 'document_srl', $bid->document_srl);
            //$this->setRedirectUrl($returnUrl);
        }


        function updateProjectBidInfo($document_srl) {
            // pa_projects 테이블의 bid_count, bid_average 값을 업데이트.
            $oPaModel = &getModel('pa');
            $project = $oPaModel->getProject($document_srl);
            $bid_list = $oPaModel->getBidList($document_srl);

            if(!empty($bid_list)) { // 입찰들이 있다면 bid_count 와 bid_average 를 구해서 업데이트.
                $total_bid_price = 0;
                $bid_count = count($bid_list);
                foreach($bid_list as $val) {
                    $total_bid_price += $val->bid_price;
                }
                $project->bid_count = $bid_count;
                $project->bid_average =  $total_bid_price / $bid_count;
                $output = executeQuery('pa.updateProject', $project);
            }
            else { // 입찰들이 없다면 0, 0 으로 업데이트.
                $project->bid_count = 0;
                $project->bid_average = 0;
                $output = executeQuery('pa.updateProject', $project);
            }
            return $output;
        }



        /**
         * @brief insert comments
         **/
        function procPaInsertComment() {
            // check grant
            if(!$this->grant->write_comment) return new Object(-1, 'msg_not_permitted');
            $logged_info = Context::get('logged_info');

            // get the relevant data for inserting comment
            $obj = Context::gets('document_srl','comment_srl','parent_srl','content','password','nick_name','member_srl','email_address','homepage','is_secret','notify_message','use_html');
            $obj->module_srl = $this->module_srl;

            // check if the doument is existed
            $oDocumentModel = &getModel('document');
            $oDocument = $oDocumentModel->getDocument($obj->document_srl);
            if(!$oDocument->isExists()) return new Object(-1,'msg_not_permitted');

            // For anonymous use, remove writer's information and notifying information
            if($this->module_info->use_anonymous == 'Y') {
                $obj->notify_message = 'N';
                $this->module_info->admin_mail = '';

                $obj->member_srl = -1*$logged_info->member_srl;
                $obj->email_address = $obj->homepage = $obj->user_id = '';
                $obj->user_name = $obj->nick_name = 'anonymous';
                $bAnonymous = true;
            }
            else
            {
                $bAnonymous = false;
            }

            // generate comment  module model object
            $oCommentModel = &getModel('comment');

            // generate comment module controller object
            $oCommentController = &getController('comment');

            // check the comment is existed
            // if the comment is not existed, then generate a new sequence
            if(!$obj->comment_srl) {
                $obj->comment_srl = getNextSequence();
            } else {
                $comment = $oCommentModel->getComment($obj->comment_srl, $this->grant->manager);
            }

            // if comment_srl is not existed, then insert the comment
            if($comment->comment_srl != $obj->comment_srl) {

                // parent_srl is existed
                if($obj->parent_srl) {
                    $parent_comment = $oCommentModel->getComment($obj->parent_srl);
                    if(!$parent_comment->comment_srl) return new Object(-1, 'msg_invalid_request');

                    $output = $oCommentController->insertComment($obj, $bAnonymous);

                // parent_srl is not existed
                } else {
                    $output = $oCommentController->insertComment($obj, $bAnonymous);
                }

		/*
                // send an email
                if($output->toBool() && $this->module_info->admin_mail) {
                    $oMail = new Mail();
                    $oMail->setTitle($oDocument->getTitleText());
                    $oMail->setContent( sprintf("From : <a href=\"%s#comment_%d\">%s#comment_%d</a><br/>\r\n%s", getFullUrl('','document_srl',$obj->document_srl),$obj->comment_srl, getFullUrl('','document_srl',$obj->document_srl), $obj->comment_srl, $obj->content));
                    $oMail->setSender($obj->user_name, $obj->email_address);

                    $target_mail = explode(',',$this->module_info->admin_mail);
                    for($i=0;$i<count($target_mail);$i++) {
                        $email_address = trim($target_mail[$i]);
                        if(!$email_address) continue;
                        $oMail->setReceiptor($email_address, $email_address);
                        $oMail->send();
                    }
                }
		*/

            // update the comment if it is not existed
            } else {
				// check the grant
				if(!$comment->isGranted()) return new Object(-1,'msg_not_permitted');

                $obj->parent_srl = $comment->parent_srl;
                $output = $oCommentController->updateComment($obj, $this->grant->manager);
                $comment_srl = $obj->comment_srl;
            }
            if(!$output->toBool()) return $output;

            $this->setMessage('success_registed');
            $this->add('mid', Context::get('mid'));
            $this->add('document_srl', $obj->document_srl);
            $this->add('comment_srl', $obj->comment_srl);
        }

        /**
         * @brief delete the comment
         **/
        function procPaDeleteComment() {
            // get the comment_srl
            $comment_srl = Context::get('comment_srl');
            if(!$comment_srl) return $this->doError('msg_invalid_request');

            // generate comment  controller object
            $oCommentController = &getController('comment');

            $output = $oCommentController->deleteComment($comment_srl, $this->grant->manager);
            if(!$output->toBool()) return $output;

            $this->add('mid', Context::get('mid'));
            $this->add('page', Context::get('page'));
            $this->add('document_srl', $output->get('document_srl'));
            $this->setMessage('success_deleted');
        }

        /**
         * @brief delete the tracjback
         **/
        function procPaDeleteTrackback() {
            $trackback_srl = Context::get('trackback_srl');

            // generate trackback module controller object
            $oTrackbackController = &getController('trackback');
            $output = $oTrackbackController->deleteTrackback($trackback_srl, $this->grant->manager);
            if(!$output->toBool()) return $output;

            $this->add('mid', Context::get('mid'));
            $this->add('page', Context::get('page'));
            $this->add('document_srl', $output->get('document_srl'));
            $this->setMessage('success_deleted');
        }

        /**
         * @brief check the password for document and comment
         **/
        function procPaVerificationPassword() {
            // get the id number of the document and the comment
            $password = Context::get('password');
            $document_srl = Context::get('document_srl');
            $comment_srl = Context::get('comment_srl');

            $oMemberModel = &getModel('member');

            // if the comment exists
            if($comment_srl) {
                // get the comment information
                $oCommentModel = &getModel('comment');
                $oComment = $oCommentModel->getComment($comment_srl);
                if(!$oComment->isExists()) return new Object(-1, 'msg_invalid_request');

                // compare the comment password and the user input password
                if(!$oMemberModel->isValidPassword($oComment->get('password'),$password)) return new Object(-1, 'msg_invalid_password');

                $oComment->setGrant();
            } else {
                 // get the document information
                $oDocumentModel = &getModel('document');
                $oDocument = $oDocumentModel->getDocument($document_srl);
                if(!$oDocument->isExists()) return new Object(-1, 'msg_invalid_request');

                // compare the document password and the user input password
                if(!$oMemberModel->isValidPassword($oDocument->get('password'),$password)) return new Object(-1, 'msg_invalid_password');

                $oDocument->setGrant();
            }
        }

        /**
         * @brief the trigger for displaying 'view document' link when click the user ID
         **/
        function triggerMemberMenu(&$obj) {
            $member_srl = Context::get('target_srl');
            $mid = Context::get('cur_mid');

            if(!$member_srl || !$mid) return new Object();

            $logged_info = Context::get('logged_info');

            // get the module information
            $oModuleModel = &getModel('module');
			$columnList = array('module');
            $cur_module_info = $oModuleModel->getModuleInfoByMid($mid, 0, $columnList);

            if($cur_module_info->module != 'pa') return new Object();

            // get the member information
            if($member_srl == $logged_info->member_srl) {
                $member_info = $logged_info;
            } else {
                $oMemberModel = &getModel('member');
                $member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
            }

            if(!$member_info->user_id) return new Object();

            //search
            $url = getUrl('','mid',$mid,'search_target','nick_name','search_keyword',$member_info->nick_name);
            $oMemberController = &getController('member');
            $oMemberController->addMemberPopupMenu($url, 'cmd_view_own_document', '');

            return new Object();
        }


        /**
         * @brief 프로젝트 상태 변경. 현재는 doCallModuleAction 으로 호출됨.
         **/
        function procPaChangeProjectStatusOpen() {
            // Check login information
            if(!Context::get('is_logged')) return new Object(-1, 'msg_not_logged');
            $logged_info = Context::get('logged_info');

            $document_srl = (int)Context::get('document_srl');
            if(!$document_srl) $document_srl = (int)Context::get('target_srl');
            if(!$document_srl) return new Object(-1,'msg_invalid_request');

            $args = new stdClass();
            $args->document_srl = $document_srl;
            $args->project_status = 'OPEN';
            $output = $this->changeProjectStatus($args);
            if(!$output->toBool()) return $output;

            $this->setError(-1);
            $this->setMessage('success_updated');
        }
        function procPaChangeProjectStatusWorking() {
            // Check login information
            if(!Context::get('is_logged')) return new Object(-1, 'msg_not_logged');
            $logged_info = Context::get('logged_info');

            $document_srl = (int)Context::get('document_srl');
            if(!$document_srl) $document_srl = (int)Context::get('target_srl');
            if(!$document_srl) return new Object(-1,'msg_invalid_request');

            $args = new stdClass();
            $args->document_srl = $document_srl;
            $args->project_status = 'WORKING';
            $output = $this->changeProjectStatus($args);
            if(!$output->toBool()) return $output;

            $this->setError(-1);
            $this->setMessage('success_updated');
        }
        function procPaChangeProjectStatusCommplete() {
            // Check login information
            if(!Context::get('is_logged')) return new Object(-1, 'msg_not_logged');
            $logged_info = Context::get('logged_info');

            $document_srl = (int)Context::get('document_srl');
            if(!$document_srl) $document_srl = (int)Context::get('target_srl');
            if(!$document_srl) return new Object(-1,'msg_invalid_request');

            $args = new stdClass();
            $args->document_srl = $document_srl;
            $args->project_status = 'COMPLETE';
            $output = $this->changeProjectStatus($args);
            if(!$output->toBool()) return $output;

            $this->setError(-1);
            $this->setMessage('success_updated');
        }
        function procPaChangeProjectStatusCancel() {
            // Check login information
            if(!Context::get('is_logged')) return new Object(-1, 'msg_not_logged');
            $logged_info = Context::get('logged_info');

            $document_srl = (int)Context::get('document_srl');
            if(!$document_srl) $document_srl = (int)Context::get('target_srl');
            if(!$document_srl) return new Object(-1,'msg_invalid_request');

            $args = new stdClass();
            $args->document_srl = $document_srl;
            $args->project_status = 'CANCEL';
            $output = $this->changeProjectStatus($args);
            if(!$output->toBool()) return $output;

            $this->setError(-1);
            $this->setMessage('success_updated');
        }
        function procPaChangeProjectStatusExpire() {
            // Check login information
            if(!Context::get('is_logged')) return new Object(-1, 'msg_not_logged');
            $logged_info = Context::get('logged_info');

            $document_srl = (int)Context::get('document_srl');
            if(!$document_srl) $document_srl = (int)Context::get('target_srl');
            if(!$document_srl) return new Object(-1,'msg_invalid_request');

            $args = new stdClass();
            $args->document_srl = $document_srl;
            $args->project_status = 'EXPIRE';
            $output = $this->changeProjectStatus($args);
            if(!$output->toBool()) return $output;

            $this->setError(-1);
            $this->setMessage('success_updated');
        }
        function changeProjectStatus($args) {
            $output = executeQuery('pa.updateProjectStatus', $args);
            return $output;
        }


    }
?>
