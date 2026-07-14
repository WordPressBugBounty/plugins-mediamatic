<?php
namespace Mediamatic;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class REST
 *
 * Registers REST API endpoints.
 */
class REST
{

    /**
     * @var Folder_Manager
     */
    private $folder_manager;

    public function __construct($folder_manager)
    {
        $this->folder_manager = $folder_manager;
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes()
    {
        $namespace = 'mediamatic/v1';

        // Get all folders
        register_rest_route($namespace, '/folders', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_folders'],
            'permission_callback' => [Permissions::class, 'check_api_permissions'],
        ]);

        // Create folder
        register_rest_route($namespace, '/folders', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'create_folder'],
            'permission_callback' => [Permissions::class, 'check_api_permissions'],
            'args' => [
                'name' => ['required' => true, 'type' => 'string'],
                'parent_id' => ['required' => false, 'type' => 'integer', 'default' => 0],
                'color' => ['required' => false, 'type' => 'string', 'default' => ''],
                'post_type' => ['required' => false, 'type' => 'string', 'default' => 'attachment'],
            ],
        ]);

        // Update folder
        register_rest_route($namespace, '/folders/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::EDITABLE,
            'callback' => [$this, 'update_folder'],
            'permission_callback' => [Permissions::class, 'check_api_permissions'],
        ]);

        // Delete folder
        register_rest_route($namespace, '/folders/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [$this, 'delete_folder'],
            'permission_callback' => [Permissions::class, 'check_api_permissions'],
        ]);



        // Reorder folder
        register_rest_route($namespace, '/folders/reorder', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'reorder_folder'],
            'permission_callback' => [Permissions::class, 'check_api_permissions'],
            'args' => [
                'folder_id' => ['required' => true, 'type' => 'integer'],
                'target_parent_id' => ['required' => true, 'type' => 'integer'],
                'target_order_index' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        // Assign media
        register_rest_route($namespace, '/media/assign', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'assign_media'],
            'permission_callback' => function () {
                return current_user_can('upload_files');
            },
        ]);

        // Media counts
        register_rest_route($namespace, '/media/counts', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_folder_counts'],
            'permission_callback' => [Permissions::class, 'check_api_permissions'],
            'args' => [
                'post_type' => ['required' => false, 'type' => 'string', 'default' => 'attachment'],
                'recursive' => ['required' => false, 'type' => 'integer', 'default' => 0],
            ],
        ]);



        // Replace media
        register_rest_route($namespace, '/media/(?P<id>\d+)/replace', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'replace_media'],
            'permission_callback' => [Permissions::class, 'check_api_permissions'],
        ]);

        // User state (active folder tracking)
        register_rest_route($namespace, '/user/active-folder', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'save_active_folder'],
            'permission_callback' => function () {
                return current_user_can('upload_files');
            },
            'args' => [
                'folder_id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);
    }

    public function save_active_folder(\WP_REST_Request $request)
    {
        $folder_id = $request->get_param('folder_id');
        $user_id = get_current_user_id();

        if ($user_id) {
            update_user_meta($user_id, 'mediamatic_active_folder', $folder_id);
        }

        return rest_ensure_response(['success' => true]);
    }

    public function get_folders(\WP_REST_Request $request)
    {
        $post_type = $request->get_param('post_type') ?: 'attachment';
        $folders = $this->folder_manager->get_folders(['post_type' => sanitize_key($post_type)]);
        return rest_ensure_response($folders);
    }

    public function create_folder(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        // Also pick up post_type from query/body params
        if (empty($params['post_type'])) {
            $params['post_type'] = $request->get_param('post_type') ?: 'attachment';
        }
        $id = $this->folder_manager->create_folder($params);

        if (is_wp_error($id)) {
            return $id;
        }

        $folder = $this->folder_manager->get_folder($id);
        return rest_ensure_response($folder);
    }

    public function update_folder(\WP_REST_Request $request)
    {
        $id = $request->get_param('id');
        $params = $request->get_json_params();
        $result = $this->folder_manager->update_folder($id, $params);

        if (is_wp_error($result)) {
            return $result;
        }

        $folder = $this->folder_manager->get_folder($id);
        return rest_ensure_response($folder);
    }

    public function delete_folder(\WP_REST_Request $request)
    {
        $id = $request->get_param('id');
        $result = $this->folder_manager->delete_folder($id, true);

        if (is_wp_error($result)) {
            return $result;
        }

        return new \WP_REST_Response(['deleted' => true], 200);
    }



    public function reorder_folder(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        $folder_id = isset($params['folder_id']) ? $params['folder_id'] : 0;
        $target_parent_id = isset($params['target_parent_id']) ? $params['target_parent_id'] : 0;
        $target_order_index = isset($params['target_order_index']) ? $params['target_order_index'] : 0;

        $result = $this->folder_manager->reorder_folder($folder_id, $target_parent_id, $target_order_index);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response(['success' => true]);
    }

    public function assign_media(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $attachment_ids = isset($params['attachments']) ? $params['attachments'] : [];
        $folder_ids = isset($params['folders']) ? $params['folders'] : [];

        if (empty($attachment_ids)) {
            return new \WP_Error('invalid_params', __('No attachments provided.', 'mediamatic'));
        }

        foreach ($attachment_ids as $id) {
            if (!current_user_can('edit_post', $id)) {
                return new \WP_Error('forbidden', __('You cannot edit this media.', 'mediamatic'), ['status' => 403]);
            }
        }

        foreach ($folder_ids as $folder_id) {
            if ($folder_id > 0 && !Permissions::user_can_edit_folder($folder_id)) {
                return new \WP_Error('forbidden', __('You cannot assign to this folder.', 'mediamatic'), ['status' => 403]);
            }
        }

        $this->folder_manager->set_attachments_folders($attachment_ids, $folder_ids, false);

        return rest_ensure_response(['success' => true]);
    }

    public function get_folder_counts(\WP_REST_Request $request)
    {
        $post_type = sanitize_key($request->get_param('post_type') ?: 'attachment');
        return rest_ensure_response($this->folder_manager->get_folder_counts($post_type));
    }



    public function replace_media(\WP_REST_Request $request)
    {
        $attachment_id = $request->get_param('id');
        $files = $request->get_file_params();

        if (empty($files['file'])) {
            return new \WP_Error('no_file', __('No file uploaded.', 'mediamatic'), ['status' => 400]);
        }

        $options = [
            'replace_type' => sanitize_text_field($request->get_param('replace_type') ?: 'replace'),
            'timestamp_replace' => sanitize_text_field($request->get_param('timestamp_replace') ?: 'keep'),
            'custom_date' => sanitize_text_field($request->get_param('custom_date') ?: '')
        ];

        $result = Media_Replacer::replace($attachment_id, $files['file'], $options);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response(['success' => true]);
    }
}
