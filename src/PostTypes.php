<?php

namespace BooksPlugin;

class PostTypes
{
    public function __construct()
    {
        add_action('init', [$this, 'register_book_post_type']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('add_meta_boxes', [$this, 'add_isbn_meta_box']);
        add_action('save_post_book', [$this, 'save_isbn_meta']);
    }

    /**
     * Create Book post-type.
     */
    public function register_book_post_type(): void
    {
        $labels = [
            'name'                  => _x('Books', 'post type general name', 'books-plugin'),
            'singular_name'         => _x('Book', 'post type singular name', 'books-plugin'),
            'menu_name'             => _x('Books', 'admin menu', 'books-plugin'),
            'name_admin_bar'        => _x('Book', 'add new on admin bar', 'books-plugin'),
            'add_new'               => _x('Add New', 'book', 'books-plugin'),
            'add_new_item'          => __('Add New Book', 'books-plugin'),
            'new_item'              => __('New Book', 'books-plugin'),
            'edit_item'             => __('Edit Book', 'books-plugin'),
            'view_item'             => __('View Book', 'books-plugin'),
            'all_items'             => __('All Books', 'books-plugin'),
            'search_items'          => __('Search Books', 'books-plugin'),
            'parent_item_colon'     => __('Parent Books:', 'books-plugin'),
            'not_found'             => __('No books found.', 'books-plugin'),
            'not_found_in_trash'    => __('No books found in Trash.', 'books-plugin'),
        ];

        $args = [
            'labels'                => $labels,
            'public'                => true,
            'publicly_queryable'    => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'query_var'             => true,
            'rewrite'               => ['slug' => 'book'],
            'capability_type'       => 'post',
            'has_archive'           => true,
            'hierarchical'          => false,
            'menu_position'         => 2,
            'supports'              => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments'],
            'menu_icon'             => 'dashicons-book-alt',

        ];

        register_post_type('book', $args);
    }

    /**
     * Register Publisher & Author Terms.
     */
    public function register_taxonomies(): void
    {
        register_taxonomy('publisher', ['book'], [
            'hierarchical'          => false,
            'labels'                => [
                'name'                  => _x('Publishers', 'taxonomy general name', 'books-plugin'),
                'singular_name'         => _x('Publisher', 'taxonomy singular name', 'books-plugin'),
                'search_items'          => __('Search Publishers', 'books-plugin'),
                'all_items'             => __('All Publishers', 'books-plugin'),
                'parent_item'           => __('Parent Publisher', 'books-plugin'),
                'parent_item_colon'     => __('Parent Publisher:', 'books-plugin'),
                'edit_item'             => __('Edit Publisher', 'books-plugin'),
                'update_item'           => __('Update Publisher', 'books-plugin'),
                'add_new_item'          => __('Add New Publisher', 'books-plugin'),
                'new_item_name'         => __('New Publisher Name', 'books-plugin'),
                'menu_name'             => __('Publishers', 'books-plugin'),
            ],
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
            'rewrite'               => ['slug' => 'publisher'],
        ]);

        register_taxonomy('author', ['book'], [
            'hierarchical'          => false,
            'labels'                => [
                'name'                  => _x('Authors', 'taxonomy general name', 'books-plugin'),
                'singular_name'         => _x('Author', 'taxonomy singular name', 'books-plugin'),
                'search_items'          => __('Search Authors', 'books-plugin'),
                'all_items'             => __('All Authors', 'books-plugin'),
                'parent_item'           => __('Parent Author', 'books-plugin'),
                'parent_item_colon'     => __('Parent Author:', 'books-plugin'),
                'edit_item'             => __('Edit Author', 'books-plugin'),
                'update_item'           => __('Update Author', 'books-plugin'),
                'add_new_item'          => __('Add New Author', 'books-plugin'),
                'new_item_name'         => __('New Author Name', 'books-plugin'),
                'menu_name'             => __('Authors', 'books-plugin'),
            ],
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
            'rewrite'               => ['slug' => 'author'],
        ]);
    }

    public function add_isbn_meta_box(): void
    {
        add_meta_box(
            'book_isbn',
            __('ISBN', 'books-plugin'),
            [$this, 'isbn_meta_box_callback'],
            'book',
            'side',
            'high'
        );
    }

    /**
     * ISBN metabox.
     */
    public function isbn_meta_box_callback($post): void
    {
        wp_nonce_field('book_isbn_nonce', 'book_isbn_nonce');
        $value = get_post_meta($post->ID, '_book_isbn', true);
        echo '<label for="book_isbn">' . __('ISBN Number:', 'books-plugin') . '</label> ';
        echo '<input type="text" id="book_isbn" name="book_isbn" value="' . esc_attr($value) . '" size="13" />';
    }

    /**
     * Handling ISBN Data, including sanitize, and also delete record if isbn is empty.
     */
    public function save_isbn_meta($post_id): void
    {
        if (!isset($_POST['book_isbn_nonce']) || !wp_verify_nonce($_POST['book_isbn_nonce'], 'book_isbn_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!isset($_POST['book_isbn'])) {
            return;
        }

        $isbn = sanitize_text_field($_POST['book_isbn']);
        update_post_meta($post_id, '_book_isbn', $isbn);

        $post_status = get_post_status($post_id);

        if ($post_status == 'auto-draft') {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'books_info';

        if (!empty($isbn)) {
            $existing_entry = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE post_id = %d",
                $post_id
            ));

            if ($existing_entry) {
                $wpdb->update(
                    $table_name,
                    ['isbn' => $isbn],
                    ['post_id' => $post_id],
                    ['%s'],
                    ['%d']
                );
            } else {
                $wpdb->insert(
                    $table_name,
                    [
                        'post_id' => $post_id,
                        'isbn'    => $isbn,
                    ],
                    ['%d', '%s']
                );
            }
        } else {
            $wpdb->delete(
                $table_name,
                ['post_id' => $post_id],
                ['%d']
            );
        }
    }
}