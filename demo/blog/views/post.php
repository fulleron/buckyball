
<?php if (Blog::user()): ?>

<form class="post-form update-post-form" method="post" action="<?php echo BApp::href('posts/'.$this->post->id) ?>"><fieldset>
    <h2>Update the Post</h2>
    <label for="title">Title:</label>
    <input type="text" id="title" name="title" value="<?php echo $this->q($this->post->title) ?>"/><br/>
    <label for="preview">Preview:</label>
    <textarea id="preview" name="preview"><?php echo $this->q($this->post->preview) ?></textarea><br/>
    <label for="body">Content:</label>
    <textarea id="body" name="body"><?php echo $this->q($this->post->body) ?></textarea><br/>
    <label>&nbsp;</label>
    <input type="submit" name="action" value="Update the Post"/>
    <input type="submit" name="action" value="Delete" onclick="return confirm('Are you sure?')"/>
</fieldset></form>

<?php endif ?>

<div class="post-entry">
    <h1><?php echo Blog::q($this->post->title) ?></h1>
    <p><?php echo nl2br(Blog::q($this->post->body)) ?></p>
    <cite>Posted by admin on <?php echo $this->post->posted_at ?></cite>
</div>

<a name="comments"></a>
<h2>Comments</h2>
<?php if ($this->comments): ?>

<?php foreach ($this->comments as $comment): ?>
    <div class="post-comment <?php echo !$comment->approved ? 'unapproved' : '' ?>">
        <blockquote><?php echo nl2br(Blog::q($comment->body)) ?></blockquote>
        <cite>by <?php echo $this->q($comment->name) ?> on <?php echo $comment->posted_at ?></cite>
        <?php if (Blog::user()): ?>
            <form class="update-comments-form" method="post" action="<?php echo BApp::href('posts/'.$this->post->id.'/comments/'.$comment->id) ?>"><fieldset>
                <input type="hidden" name="approved" value="<?php echo $comment->approved ? 0 : 1 ?>"/>
                <input type="submit" name="action" value="<?php echo $comment->approved ? 'Unapprove' : 'Approve' ?>"/>
                <input type="submit" name="action" value="Delete" onclick="return confirm('Are you sure?')"/>
            </fieldset></form>
        <?php endif ?>
    </div>
<?php endforeach ?>

<?php else: ?>

<p>Be the first to comment!</p>

<?php endif ?>

<form class="post-form create-comment-form" method="post" action="<?php echo BApp::href('posts/'.$this->post->id.'/comments/') ?>"><fieldset>
    <h2>Add a comment</h2>
    <label for="name">Your name:</label>
    <input type="text" id="name" name="name"/><br/>
    <label for="body">Comment:</label>
    <textarea id="body" name="body"></textarea><br/>
    <label>&nbsp;</label>
    <input type="submit" value="Submit your Comment"/>
</fieldset></form>