<?php if (Blog::user()): ?>
    <form class="post-form create-post-form" method="post" action="<?php echo BApp::href('posts/') ?>"><fieldset>
        <h2>Submit a Post</h2>
        <label for="title">Post Title:</label>
        <input type="text" id="title" name="title"/><br/>
        <label for="preview">Preview:</label>
        <textarea id="preview" name="preview"></textarea><br/>
        <label for="body">Contents:</label>
        <textarea id="body" name="body"></textarea><br/>
        <label>&nbsp;</label>
        <input type="submit" value="Submit a Post"/>
    </fieldset></form>
<?php endif ?>

<?php if (!$this->posts): ?>

<p>No posts found</p>

<?php else: ?>

<?php foreach ($this->posts as $post): ?>
    <div class="post-entry">
        <h2><a href="<?php echo BApp::href('posts/'.$post->id) ?>"><?php echo Blog::q($post->title) ?></a></h2>
        <p class="post-preview"><?php echo Blog::q($post->preview ? $post->preview : $post->body) ?></p>
        <cite>Posted by admin on <?php echo $post->posted_at ?></cite>
        <a class="comment-count" href="<?php echo BApp::href('posts/'.$post->id.'#comments') ?>"><?php echo $post->comment_count ? $post->comment_count.' comments' : 'Be first to comment!' ?></a>
    </div>
<?php endforeach ?>

<?php endif ?>