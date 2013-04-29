<?php
App::uses('CoursesAppController', 'Courses.Controller');
/**
 * Courses Controller
 *
 * @property Course $Course
 */
class CoursesController extends CoursesAppController {

	public $name = 'Courses';
	public $uses = 'Courses.Course';
	public $components = array('RequestHandler');

/**
 * index method
 *
 * @return void
 */
	public function index() {
		$this->Course->recursive = 0;
//		$this->paginate = array(
//			'conditions' => array('Course.parent_id' => null)
//		);
		$this->set('courses', $this->paginate());
	}


	public function dashboard() {
		if ( in_array($this->Auth->user('user_role_id'), array(1, 8)) ) {
			// teachers
			$this->set('courses', $this->Course->find('all', array(
				'conditions' => array(
					'Course.creator_id' => $this->Auth->user('id'),
					'Course.type' => 'course'
				),
				'order' => array('Course.end' => 'ASC')
			)));
			$this->view = 'teacher_dashboard';
		} else {
			// students
			$this->set('courses', $this->Course->CourseUser->find('all', array(
				'conditions' => array('CourseUser.user_id' => $this->Auth->user('id')),
				'contain' => array(
					'Course' => array(
					)
				)
			)));

			$this->view = 'student_dashboard';
		}
	}
	
/**
 * view method
 *
 * @param string $id
 * @return void
 */
	public function view($id = null) {
		$this->Course->id = $id;
		if (!$this->Course->exists()) {
			throw new NotFoundException(__('Invalid course'));
		}
		
		$course = $this->Course->find('first', array(
			'conditions' => array('Course.id' => $this->Course->id),
			'contain' => array(
				'Form',
				'Lesson',
				'Media',
				'Grade',
				'Task' => array(
					'conditions' => array('Task.parent_id' => ''),
					'ChildTask'
				),
				'Series' => array(
					'fields' => array('Series.id', 'Series.name', 'Series.is_sequential')
				)
			)
		));
		
		$courseUsers = $this->Course->CourseUser->find('all', array(
			'conditions' => array('CourseUser.course_id' => $this->Course->id),
			'contain' => array('User')
		));
		$courseUsers = Set::combine($courseUsers, '{n}.User.id', '{n}');
		
		if ( !isset($courseUsers[$this->Session->read('Auth.User.id')]) ) {
			$this->layout = 'guest_view';
		} elseif ( $course['Course']['creator_id'] == $this->Session->read('Auth.User.id') ) {
			$this->view = 'teacher_view';
		} else {
			$this->view = 'registered_view';
		}

		$this->set('courseUsers', $courseUsers);
		$this->set('course', $course);
		$this->set('title_for_layout', $course['Course']['name'] . ' | ' . __SYSTEM_SITE_NAME);
	}

/**
 * add method
 * 
 * @return void
 */
	public function add() {
		if ($this->request->is('post')) {
			$this->Course->create();
			$this->request->data['Course']['creator_id'] = $this->Auth->user('id');
			if ($this->Course->save($this->request->data)) {
				$this->Session->setFlash(__('The course has been created'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The course could not be created. Please, try again.'));
			}
		}
		$series = $this->Course->Series->find('list');
		$this->set(compact('series'));
		$this->render('add');
	}

/**
 * edit method
 *
 * @param string $id
 * @return void
 */
	public function edit($id = null) {
		$this->Course->id = $id;
		if (!$this->Course->exists()) {
			throw new NotFoundException(__('Invalid course'));
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			if ($this->Course->save($this->request->data)) {
				$this->Session->setFlash(__('The course has been saved'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The course could not be saved. Please, try again.'));
			}
		} else {
			$this->request->data = $this->Course->read(null, $id);
		}
		$parentCourses = $this->Course->Lesson->find('list');
		$this->set(compact('parentCourses'));
	}

/**
 * delete method
 *
 * @param string $id
 * @return void
 */
	public function delete($id = null) {
		if (!$this->request->is('post')) {
			throw new MethodNotAllowedException();
		}
		$this->Course->id = $id;
		if (!$this->Course->exists()) {
			throw new NotFoundException(__('Invalid course'));
		}
		if ($this->Course->delete()) {
			$this->Session->setFlash(__('Course deleted'));
			$this->redirect(array('action' => 'index'));
		}
		$this->Session->setFlash(__('Course was not deleted'));
		$this->redirect(array('action' => 'index'));
	}
	
	public function register($id = null) {
		$this->Course->id = $id;
		if (!$this->Course->exists()) {
			throw new NotFoundException(__('Invalid course'));
		}
		// add this user to course_users
		$newRegistration = $this->Course->CourseUser->create();
		$newRegistration['CourseUser']['user_id'] = $this->Auth->user('id');
		$newRegistration['CourseUser']['course_id'] = $this->Course->id;
		if ( $this->Course->CourseUser->save($newRegistration) ) {
			$this->Session->setFlash(__('Registration Successful.'));
			$this->redirect(array('action' => 'view', $this->Course->id));
		}
	}
	
	public function unregister($id = null) {
		$this->Course->id = $id;
		if (!$this->Course->exists()) {
			throw new NotFoundException(__('Invalid course'));
		}
		// remove this user to course_users
		$unregistered = $this->Course->CourseUser->deleteAll(array(
			'CourseUser.user_id' => $this->Auth->user('id'),
			'CourseUser.course_id' => $this->Course->id
		));
		if ( $unregistered ) {
			$this->Session->setFlash(__('You are no longer registered for this course.'));
			$this->redirect(array('action' => 'view', $this->Course->id));
		}
	}
	
	public function gradebook($id = null) {
		$this->Course->id = $id;
		if (!$this->Course->exists()) {
			throw new NotFoundException(__('Invalid course'));
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			if ($this->Course->save($this->request->data)) {
				$this->Session->setFlash(__('The gradebook has been saved'));
				$this->redirect(array('action' => 'index'));
			}
		} else {
			$this->request->data = $this->Course->read(null, $id);
		}
		$parentCourses = $this->Course->Lesson->find('list');
		$this->set(compact('parentCourses'));
	}
	
	/**
	 * select what type of thing to assign
	 * 
	 * @param type $param
	 */
	public function assign($param = null, $foreignKey = null) {
//		if ( !empty($param) ) {
//			$function = '_assign_' . $param;
//			return $this->{$function};
//		}
		if ( $this->request->is('post') ) {
			$this->request->data['Task']['model'] = 'Course';
			$this->request->data['Task']['foreign_key'] = $foreignKey;
			if ( $this->Course->Task->save($this->request->data) ) {
				$this->Session->setFlash(__('The assignment has been saved.'));
				$this->redirect(array('action' => 'view', $foreignKey));
			}
		}
	}
	
	protected function _assign_quiz() {
		
	}
	
	/**
	 * View a "task
	 * 
	 * @param string $id The Task ID
	 */
	public function assignment($id, $completed = null) {
		if ( $id ) {
			
			$task = $this->Course->Task->find('first', array(
				'conditions' => array('Task.id' => $id),
				//'contain' => 'Course'
			));
			
			if ( $completed === 'completed' ) {
				// create a child task of this task, assigned to current user, marked complete
				$completedAssignment = $this->Course->Task->create();
				$completedAssignment['Task']['parent_id'] = $id;
				$completedAssignment['Task']['model'] = $task['Task']['model'];
				$completedAssignment['Task']['foreign_key'] = $task['Task']['foreign_key'];
				$completedAssignment['Task']['is_completed'] = '1';
				$completedAssignment['Task']['assignee_id'] = $this->Auth->user('id');
				$completedAssignment['Task']['completed_date'] = date("Y-m-d");
//				debug($completedAssignment); break;
				if ( $this->Course->Task->save($completedAssignment) ) {
					$this->Session->setFlash(__('The assignment has been saved.'));
					$this->redirect(array('action' => 'view', $task['Task']['foreign_key']));
				}
			}
			
			$this->set(compact('task'));
			$this->set('title_for_layout', $task['Task']['name'] . ' | ' . __SYSTEM_SITE_NAME);
		} else {
			$this->Session->setFlash(__('Assignment ID not specified.'));
			$this->redirect($this->referer());
		}
	}
	
	/**
	 * 
	 * @param integer $courseId The ID of the Course that these messages belong to
	 */
	public function message($courseId) {
		// find the current messages
//		$this->paginate = array(
//			'conditions' => array(
//				'Message.model' => 'Course',
//				'Message.foreign_key' => $courseId,
//				),
//			'fields' => array(
//				'id',
//				'title',
//				'created',
//				'body',
//				),
//			'contain' => array(
//				'Sender' => array(
//					'fields' => array(
//						'full_name',
//						),
//					),
//				),
//			'order' => array(
//				'Message.created' => 'desc',
//				),
//			);
//		$this->set('messages', $this->paginate('Message'));
		
		// set the foreign_key
		$this->request->data['Message']['foreign_key'] = !empty($courseId) ? $courseId : null;
		
		// setup the array of possible recipients
		$users = $this->Course->CourseUser->find('all', array(
			'conditions' => array('CourseUser.course_id' => $courseId),
			'contain' => array('User' => array('fields' => array('id', 'last_name', 'first_name')))
		));
		$users = Set::combine($users, '{n}.User.id', '{n}');
		foreach ( $users as &$user ) {
			$user = $user['User']['last_name'] . ', ' . $user['User']['first_name'];
		}
		$this->set(compact('users'));
	}
	
}
