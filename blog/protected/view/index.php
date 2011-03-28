<?php if (Blog::user()): ?>
    <form class="create-post-form" method="post" action="<?php echo BApp::baseUrl().'/posts/' ?>"><fieldset>
        <h2>Submit a post</h2>
        <label for="title">Post Title:</label>
        <input type="text" id="title" name="title"/>
        <label for="preview">Preview:</label>
        <textarea id="body" id="preview" name="preview"></textarea>
        <label for="body">Contents:</label>
        <textarea id="body" id="body" name="body"></textarea>
        <br/>
        <input type="submit" value="Post"/>
    </fieldset></form>
<?php endif ?>

<?php if (!$this->posts): ?>

<p>No posts found</p?

<?php else: ?>

<?php foreach ($this->posts as $post): ?>
    <h2><a href="<?php echo BApp::baseUrl().'/posts/'.$post->id ?>"><?php echo $post->title ?></a></h2>
    <p class="post-preview"><?php echo $post->preview ? $post->preview : $post->body ?></p>
    <a class="comment-count" href="<?php echo BApp::baseUrl().'/posts/'.$post->id.'#comments' ?>"><?php echo $post->comment_count ? $post->comment_count.' comments' : 'Be first to comment!' ?></a>
<?php endforeach ?>

<?php endif ?>