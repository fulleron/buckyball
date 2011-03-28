<?php

class Blog
{
    static public function init()
    {
        BFrontController::service()
        // public access
            ->route('GET /', array('Blog_Public', 'index'))
            ->route('GET /posts/:post_id', array('Blog_Public', 'post'))
            ->route('POST /posts/:post_id/comments/', array('Blog_Public', 'new_comment'))

        // admin access
            ->route('POST /login', array('Blog_Admin', 'login'))
            ->route('POST /posts/', array('Blog_Admin', 'new_post'))
            ->route('POST /posts/:post_id', array('Blog_Admin', 'update_post'))
            ->route('POST /posts/:post_id/comments/:com_id', array('Blog_Admin', 'update_comment'))
            ->route('GET /comments/', array('Blog_Admin', 'comments'))
            ->route('GET /logout', array('Blog_Admin', 'logout'))
        ;

        BLayout::service()
            ->allViews('protected/view')
            ->view('head', array('view_class'=>'BViewHead'))
            ->view('body', array('view_class'=>'BViewList'))
        ;

        BEventRegistry::service()
            ->observe('layout.render.before', array('Blog', 'layout_render_before'))
        ;
    }

    static public function user()
    {
        return BSession::service()->data('user');
    }

    static public function redirect($url, $status, $msg)
    {
        BResponse::service()->redirect(BApp::baseUrl().$url.'?status='.$status.'&msg='.urlencode($msg));
    }

    static public function q($str)
    {
        return strip_tags($str, '<a><p><b><i><u><ul><ol><li><strong><em><br><img>');
    }

    public function layout_render_before($args)
    {
        $layout = BLayout::service();
        $layout->view('head')->css('css/common.css');

        $request = BRequest::service();
        if ($request->get('status') && $request->get('msg')) {
            $layout->view('main')->messageClass = $request->get('status');
            $layout->view('main')->message = $request->get('msg');
        }
    }
}

class Blog_Public extends BActionController
{
    public function action_index()
    {
        $layout = BLayout::service();
        $layout->view('body')->append('index');
        $layout->view('index')->posts = BModel::factory('BlogPost')
            ->select('id')->select('title')->select('preview')->select('posted_at')
            ->select_expr('(select count(*) from blog_post_comment where post_id=blog_post.id and approved)', 'comment_count')
            ->order_by_desc('posted_at')
            ->find_many();

        BResponse::service()->output();
    }

    public function action_post()
    {
        $postId = BRequest::service()->params('post_id');
        $post = BModel::factory('BlogPost')->find_one($postId);
        if (!$post) {
            Blog::redirect('/', 'error', "Post not found!");
            #$this->forward('noroute');
        }
        $commentsOrm = BModel::factory('BlogPostComment')
            ->select('id')->select('name')->select('body')->select('posted_at')->select('approved')
            ->where('post_id', $postId)
            ->order_by_asc('posted_at');
        if (!Blog::user()) {
            $commentsOrm->where('approved', 1);
        }
        $comments = $commentsOrm->find_many();

        $layout = BLayout::service();
        $layout->view('body')->append('post');
        $layout->view('post')->post = $post;
        $layout->view('post')->comments = $comments;

        BResponse::service()->output();
    }

    public function action_new_comment()
    {
        $request = BRequest::service();
        try {
            if (!$request->post('name') || !$request->post('body')) {
                throw new Exception("Not enough information for comment!");
            }
            $post = BModel::factory('BlogPost')->find_one($request->params('post_id'));
            if (!$post) {
                throw new Exception("Invalid post");
            }
            $comment = BModel::factory('BlogPostComment')->create();
            $comment->post_id = $post->id;
            $comment->name = $request->post('name');
            $comment->body = $request->post('body');
            $comment->posted_at = BDb::now();
            $comment->approved = Blog::user() ? 1 : 0;
            $comment->save();

            $msg = "Thank you for your comment!".(!Blog::user() ? " It will appear after approval." : "");
            Blog::redirect('/posts/'.$post->id, 'success',  $msg);
        } catch (Exception $e) {
            Blog::redirect('/', 'error', $e->getMessage());
        }
    }

    public function action_noroute()
    {
        BLayout::service()->view('body')->append('404');
        BResponse::service()->status(404);
    }
}

class Blog_Admin extends BActionController
{
    public function authorize($args=array())
    {
        return $this->_action=='login' || Blog::user()=='admin';
    }

    public function action_login()
    {
        $request = BRequest::service();
        try {
            if (!($request->post('username')=='admin' && $request->post('password')=='admin')) {
                throw new Exception("Invalid user name or password");
            }
            BSession::service()->data('user', 'admin');
            Blog::redirect('/', 'success',  "You're logged in as admin");
        } catch (Exception $e) {
            Blog::redirect('/', 'error', $e->getMessage());
        }
    }

    public function action_logout()
    {
        BSession::service()->data('user', false);
        Blog::redirect('/', 'success', "You've been logged out");
    }

    public function action_new_post()
    {
        $request = BRequest::service();
        try {
            if (!$request->post('title') || !$request->post('body')) {
                throw new Exception("Invalid post data");
            }

            $post = BModel::factory('BlogPost')->create();
            $post->title = $request->post('title');
            $post->preview = $request->post('preview');
            $post->body = $request->post('body');
            $post->posted_at = BDb::now();
            $post->save();

            Blog::redirect('/posts/'.$post->id, 'success',  "New post has been created!");
        } catch (Exception $e) {
            Blog::redirect('/', 'error', $e->getMessage());
        }
    }

    public function action_update_post()
    {
        $request = BRequest::service();
        try {
            if (!$request->post('title') || !$request->post('body')) {
                throw new Exception("Invalid post data");
            }

            $post = BModel::factory('BlogPost')->find_one($request->params('post_id'));
            if (!$post) {
                throw new Exception("Invalid post ID");
            }
            $post->title = $request->post('title');
            $post->preview = $request->post('preview');
            $post->body = $request->post('body');
            $post->save();

            Blog::redirect('/posts/'.$post->id, 'success',  "The post has been updated!");
        } catch (Exception $e) {
            Blog::redirect('/', 'error', $e->getMessage());
        }
    }

    public function action_update_comment()
    {
        $request = BRequest::service();
        try {
            $post = BModel::factory('BlogPost')->find_one($request->params('post_id'));
            if (!$post) {
                throw new Exception("Invalid post ID");
            }
            $comment = BModel::factory('BlogPostComment')->find_one($request->params('com_id'));
            if (!$comment || $comment->post_id != $post->id) {
                throw new Exception("Invalid comment ID");
            }
            $comment->approved = $request->post('approved');
            $comment->save();

            Blog::redirect('/posts/'.$post->id, 'success',  "The comment has been updated!");
        } catch (Exception $e) {
            Blog::redirect('/', 'error', $e->getMessage());
        }
    }
}

class BlogPost extends Model
{

}

class BlogPostComment extends Model
{

}