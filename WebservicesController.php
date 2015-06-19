<?php

/**
 * Webservices Controller
 *
 * PHP version 5
 *
 * @category Controller
 * @package  Webservices
 * @version  1.0
 * @author   Prakash Saini <prakashw3expert@gmail.com>
 */
class WebservicesController extends AppController {

    /**
     * Controller name
     *
     * @var string
     * @access public
     */
    public $name = 'Webservices';

    /**
     * Components
     *
     * @var array
     * @access public
     */
    public $components = array(
        'Email', 'RequestHandler'
    );

    /**
     * Models used by the Controller
     *
     * @var array
     * @access public
     */
    public $uses = array('User');
    public $status = false;
    public $output = null;
    public $message = null;
    public $requestData = null;

    /**
     * beforeFilter
     *
     * @return void
     * @access public 
     */
    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow();
    }

    public function index() {
        $this->requestData = $this->request->query;
        //set data from post
        if ($this->request->is('post')) {
            //$this->requestData = $this->request->input('json_decode', TRUE);
            $this->requestData = $this->request->data;
        }
        if (!empty($this->requestData['action'])) {
            $fun = "_" . $this->requestData['action'];
            $this->$fun();
        }
        $this->_output();
    }

    private function _output() {
        $this->output['message'] = $this->message;
        $this->output['status'] = $this->status;
        $output = json_encode($this->output);
        header('Content-Type: application/json');
        echo $output;
        exit();
    }

    /**
     * @author 		Prakash Chand saini <prakashw3expert@gmail.com>
     * @uses		Function used to check passed parameter is in request or not  
     * @access		private
     */
    public function checkRequiredParameters($reqParameters) {
        $requestObj = array_keys($this->requestData);
        $resp = true;
        $missingParam = array();
        foreach ($reqParameters as $v) {
            if (!in_array($v, $requestObj)) {
                $resp = false;
                $missingParam[] = $v;
            }
        }
        if (!$resp) {
            $this->status = false;
            $this->message = 'Insufficient Parameters.Missing Parameters are ' . implode(', ', $missingParam);
            $this->output['message'] = $this->message;
            $this->output['status'] = $this->status;
            $output = json_encode($this->output);
            header('Content-Type: application/json');
            echo $output;
            exit();
        }
    }

    public function _login() {
        $this->checkRequiredParameters(array('email', 'password', 'device', 'device_id'));
        $loginData = $this->requestData;
        $email = $loginData['email'];
        $password = $this->Auth->password($loginData['password']);
        $output = $this->User->manualLogin($email, $password);
        $this->status = $output['status'];
        $this->message = $output['message'];
        if ($this->status) {
            $this->output['user'] = $output['data'];
        }
    }

    public function _social_connect() {
        $this->checkRequiredParameters(array('social_id', 'email', 'social_type', 'role', 'device', 'device_id'));
        $output = $this->User->socialLogin($this->requestData);
        $this->status = $output['status'];
        $this->message = $output['message'];
        if ($this->status) {
            $this->output['user'] = $output['data'];
        }
    }

    public function _register() {
        $this->request->data['User'] = $this->requestData;
        $this->checkRequiredParameters(array('email', 'password', 'role', 'device', 'device_id'));
        $this->User->create();
        App::uses('String', 'Utility');
        $randomSt = String::uuid();
        $this->request->data['User']['activation_key'] = $randomSt;
        $this->request->data['User']['role_id'] = $this->User->getRoleId($this->request->data['User']['role']);
        ;
        $this->request->data['User']['email'] = htmlspecialchars($this->request->data['User']['email']);
        if ($this->User->save($this->request->data)) {
            $this->request->data['User']['password'] = null;
            // set mail variable
            $name = $to = $this->request->data['User']['email'];
            $activelink = Router::url('/', true) . 'activate/' . $this->User->id . '/' . $randomSt;
            $siteurl = Configure::read('Site.title');
            $replace = array($name, $siteurl, $activelink);
            // Send mail on Registration
            $this->send_mail($to, 'Register', $replace);

            $userInfo = $this->User->find('first', array('conditions' =>
                array(
                    'User.id' => $this->User->id,
                ),
                'recursive' => -1,
            ));
            $this->status = true;
            $this->output['id'] = $this->User->id;
            //$this->output['user']['email'] = $this->request->data['User']['email'];
            $this->message = 'You have successfully registered an account.';
        } else {
            foreach ($this->User->invalidFields() as $key => $error) {
                $this->message = $error[0];
            }
            $this->status = false;
        }
    }

    public function _contact_us() {
        $this->checkRequiredParameters(array('name', 'email', 'type', 'message'));
        $this->loadModel('Message');
        $this->request->data['Message'] = $this->requestData;
        $this->Message->set($this->request->data);
        $output = $this->Message->add();

        if ($output === true) {
            $this->status = true;
            $this->message = "Your request has been sent.";
        } else {
            $this->status = false;
            $this->message = $output;
        }
    }

   // jobs section web services

    public function _countries() {
        $this->loadModel('Country');
        $this->status = false;
        $restult = $this->Country->find('list');
        if ($restult) {
            $this->status = true;
            $this->output['countries'] = $restult;
        }
        else{
            $this->message = 'No countries found.';
        }
    }
    
    public function _jobTypes() {
        $this->loadModel('jobType');
        $this->status = false;
        $restult = $this->jobType->find('list');
        if ($restult) {
            $this->status = true;
            $this->output['jobTypes'] = $restult;
        }
        else{
            $this->message = 'No job type found.';
        }
    }
    
    public function _jobs() {
        //$this->checkRequiredParameters(array('name', 'email', 'type', 'message'));
        $this->loadModel('Message');
        //pr($this->request->data);die;
        $this->loadModel('Job');
        $this->Job->set($this->request->data);
        $this->status = false;
        $restult = $this->Job->searchJobs();
        if ($restult) {
            $this->status = true;
            $this->output['jobTypes'] = $restult;
        }
        else{
            $this->message = 'No job type found.';
        }
    }

}
