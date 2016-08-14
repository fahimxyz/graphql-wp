<?php

namespace Mohiohio\GraphQLWP\Type\Definition;

class PostFormat extends Tag {

    function getDescription() {
        return "The 'post_format' taxonomy was introduced in WordPress 3.1 and it is a piece of meta information that can be used by a theme to customize its presentation of a post";
    }
}
