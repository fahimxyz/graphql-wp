<?php
namespace Mohiohio\GraphQLWP;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQLRelay\Relay;

use Mohiohio\GraphQLWP\Type\Definition\WPQuery;
use Mohiohio\GraphQLWP\Type\Definition\PostStatus;
use Mohiohio\GraphQLWP\Type\Definition\WPPost;
use Mohiohio\GraphQLWP\Type\Definition\WPTerm;
use Mohiohio\GraphQLWP\Type\Definition\Post;
use Mohiohio\GraphQLWP\Type\Definition\Page;
use Mohiohio\GraphQLWP\Type\Definition\Tag;
use Mohiohio\GraphQLWP\Type\Definition\Category;
use Mohiohio\GraphQLWP\Type\Definition\PostFormat;
use Mohiohio\GraphQLWP\Type\Definition\BlogInfo;

class Schema
{
    static protected $postInterface = null;
    static protected $termInterface = null;
    static protected $query = null;
    static protected $wpQuery = null;
    static protected $postStatus = null;
    static protected $blogInfo = null;
    static protected $postTypes = [];
    static protected $termTypes = [];
    static protected $nodeDefinition = null;

    const DEFAULT_POST_TYPE = 'post';

    static function build() {
        static::init();
        return new \GraphQL\Schema(static::getQuery());
    }

    static function init() {

        static::$postTypes = apply_filters('graphql-wp/get_post_types',[
            'post' => new Post,
            'page' => new Page,
        ]);

        static::$termTypes = apply_filters('graphql-wp/get_term_types',[
            'category' => new Category,
            'tag' => new Tag,
            'post_format' => new PostFormat,
        ]);
    }

    static function getPostInterfaceType() {

        return static::$postInterface ?: static::$postInterface = new WPPost([
            'resolveType' => function ($obj) {
                if(isset(static::$postTypes[$obj->post_type])){
                    return static::$postTypes[$obj->post_type];
                }
            }
        ]);
    }

    static function getPostType($type = null) {
        return static::$postTypes[ $type ?: self::DEFAULT_POST_TYPE ];
    }

    static function getPostStatusType() {
        return static::$postStatus ?: static::$postStatus = new PostStatus();
    }

    static function getTermInterfaceType() {
        return static::$termInterface ?: static::$termInterface = new WPTerm([
            'resolveType' => function ($obj) {
                if(isset(static::$termTypes[$obj->taxonomy])){
                    return static::$termTypes[$obj->taxonomy];
                }
            }
        ]);
    }

    static function getNodeDefinition() {

        return static::$nodeDefinition ?: static::$nodeDefinition = Relay::nodeDefinitions(
        function($globalID) {

            $idComponents = Relay::fromGlobalId($globalID);

            switch ($idComponents['type']){
                case WPPost::TYPE;
                return get_post($idComponents['id']);
                case WPTerm::TYPE;
                return get_term($idComponents['id']);
                default;
                return null;
            }
        },
        function($object) {

            if ($object instanceOf \WP_Post ) {
                return static::$postTypes[$object->post_type];
            }
            if ($object instanceOf \WP_Term) {
                return static::$termTypes[$object->taxonomy];
            }
        });
    }

    static function getWPQuery() {
        return static::$wpQuery ?: static::$wpQuery = new WPQuery;
    }

    static function getBlogInfoType() {
        return static::$blogInfo ?: static::$blogInfo = new BlogInfo;
    }

    static function getQueryArgsPost() {
        return [
            'ID' => [
                'name' => 'ID',
                'description' => 'id of the post',
                'type' => Type::int()
            ],
            'slug' => [
                'name' => 'slug',
                'description' => 'name of the post',
                'type' => Type::string()
            ],
            'post_type' => [
                'name' => 'post_type',
                'description' => 'type of the post',
                'type' => Type::string()
            ]
        ];
    }

    static function postQueryResolve($root, $args) {
        if(isset($args['ID'])){
            return get_post($args['ID']);
        }

        return get_page_by_path( $args['slug'], \OBJECT, isset($args['post_type']) ? $args['post_type'] : self::DEFAULT_POST_TYPE );
    }

    static function resolvePostMeta($post, $args, $info) {
        return get_post_meta($post->ID, $info->fieldName, true);
    }

    static function getQuery() {
        return static::$query ?: static::$query = new ObjectType(static::getQuerySchema());
    }

    static function getQuerySchema() {

        $schema = apply_filters('graphql-wp/get_query_schema',[
            'name' => 'Query',
            'fields' => [
                'wp_query' => [
                    'type' => static::getWPQuery(),
                    'resolve' => function($root, $args) {
                        global $wp_query;
                        return $wp_query;
                    }
                ],
                'wp_post' => [
                    'type' => static::getPostInterfaceType(),
                    'args' => static::getQueryArgsPost(),
                    'resolve' => [get_called_class(), 'postQueryResolve']
                ],
                'term' => [
                    'type' => static::getTermInterfaceType(),
                    'args' => [
                        'id' => [
                            'type' => Type::string(),
                            'desciption' => 'Term id'
                        ]
                    ],
                    'resolve' => function($root, $args) {
                        return get_term($args['id']);
                    }
                ],
                'node' => static::getNodeDefinition()['nodeField']
            ]
        ]);

        return $schema;
    }
}
