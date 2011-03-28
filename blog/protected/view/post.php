<?php if (!Blog::user()): ?>

<h1><?php echo Blog::q($this->post->title) ?></h1>
<p><?php echo Blog::q($this->post->body) ?></p>

<?php else: ?>

<form class="update-post-form" method="post" action="<?php echo BApp::baseUrl().'/posts/'.$this->post->id ?>"><fieldset>
    <h2>Update a post</h2>
    <label for="title">Title:</label>
    <input type="text" id="title" name="title" value="<?php echo $this->q($this->post->title) ?>"/>
    <label for="preview">Preview:</label>
    <textarea id="preview" name="preview"><?php echo $this->q($this->post->preview) ?></textarea>
    <label for="body">Content:</label>
    <textarea id="body" name="body"><?php echo $this->q($this->post->body) ?></textarea>
    <input type="submit" value="Update Post"/>
</fieldset></form>

<?php endif ?>

<a name="comments"></a>
<h2>Comments</h2>
<?php if ($this->comments): ?>

<?php foreach ($this->comments as $comment): ?>
    <hr/>
    <blockquote><?php echo Blog::q($comment->body) ?></blockquote>
    <cite>by <?php echo $this->q($comment->name) ?> on <?php echo $comment->posted_at ?></cite>
    <?php if (Blog::user()): ?>
        <form class="update-comments-form" method="post" action="<?php echo BApp::baseUrl().'/posts/'.$this->post->id.'/comments/'.$comment->id ?>"><fieldset>
            <input type="checkbox" name="approved" value="1" <?php echo $comment->approved ? 'checked="checked"' : '' ?> id="approved-<?php echo $comment->id ?>"/><label for="approved-<?php echo $comment->id ?>">Approved</label>
            <input type="submit" value="Update"/>
        </fieldset></form>
    <?php endif ?>
<?php endforeach ?>

<?php else: ?>

<p>Be the first to comment!</p>

<?php endif ?>

<h2>Add a comment</h2>
<form class="create-comment-form" method="post" action="<?php echo BApp::baseUrl().'/posts/'.$this->post->id.'/comments/' ?>"><fieldset>
    <label for="name">Your name:</label>
    <input type="text" id="name" name="name"/>
    <label for="body">Comment:</label>
    <textarea id="body" name="body"></textarea>
    <input type="submit" value="Submit your comment"/>
</fieldset></form>