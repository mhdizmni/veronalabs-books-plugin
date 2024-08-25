<?php

namespace BooksPlugin;

use WP_List_Table;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class DataTable extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'book',
            'plural'   => 'books-plugin',
            'ajax'     => false
        ]);
    }

    public static function renderTable(): void
    {
        add_action('admin_menu', function() {
            $table = new self();
            $hook = add_menu_page(
                __('Books List', 'books-plugin'),
                __('Books List', 'books-plugin'),
                'manage_options',
                'books-list',
                function() use ($table) {
                    ?>
                    <div class="wrap">
                        <h1 class="wp-heading-inline"><?php _e('Books Information', 'books-plugin'); ?></h1>
                        <form id="books-table-form" method="post">
                            <?php
                            $table->prepare_items();
                            $table->display();
                            ?>
                            <input type="hidden" name="page" value="books-list">
                            <?php
                            // Adding nonce field for security
                            wp_nonce_field('bulk-books');
                            ?>
                        </form>
                    </div>
                    <?php
                },
                'dashicons-list-view',
                3
            );

            add_action("load-{$hook}", [$table, 'process_bulk_action']);
        });
    }


    public function prepare_items(): void
    {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        $this->get_bulk_actions();

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = $this->record_count();

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);

        $this->items = $this->get_books($per_page, $current_page);
    }

    public function get_columns(): array
    {
        return [
            'cb'            => '<input type="checkbox" />',
            'title'         => __('Title', 'books-plugin'),
            'isbn'          => __('ISBN', 'books-plugin'),
            'authors'       => __('Authors', 'books-plugin'),
            'publisher'     => __('Publisher', 'books-plugin'),
        ];
    }

    public function get_sortable_columns(): array
    {
        return [
//            'title'   => ['title', false],
            'isbn'    => ['isbn', false]
        ];
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'isbn':
                return esc_html($item[$column_name]);
            default:
                return print_r($item, true);
        }
    }

    public function column_title($item): string
    {
        $title = get_the_title($item['post_id']);
        $edit_link = get_edit_post_link($item['post_id']);
        $post_status = get_post_status($item['post_id']);

        $delete_url = wp_nonce_url(
            add_query_arg(
                [
                    'page'  => 'books-list',
                    'action' => 'delete',
                    'book'   => $item['post_id']
                ],
                admin_url('admin.php')
            ),
            'delete_book_' . $item['post_id']
        );

        $actions = [
            'edit'   => sprintf('<a href="%s">%s</a>', $edit_link, __('Edit', 'books-plugin')),
            'delete' => sprintf('<a href="%s">%s</a>', esc_url($delete_url), __('Delete', 'books-plugin')),
        ];

        $status_label = '';
        if ($post_status === 'draft') {
            $status_label = sprintf('<span class="post-state">%s', __('Draft', 'books-plugin'));
        }

        return sprintf('%1$s %2$s %3$s', '<a href="' . esc_url($edit_link) . '">' . esc_html($title) . '</a>', $status_label, $this->row_actions($actions));
    }

    public function column_authors($item): string
    {
        $post_id = $item['post_id'];
        $authors = get_the_terms($post_id, 'author');
        $author_links = [];

        if (!empty($authors) && !is_wp_error($authors)) {
            foreach ($authors as $author) {
                $author_name = $author->name;
                $author_link = get_edit_term_link($author->term_id, 'author');
                $author_links[] = sprintf('<a href="%s">%s</a>', $author_link, $author_name);
            }
        }

        return implode(', ', $author_links);
    }

    public function column_publisher($item): string
    {
        $post_id = $item['post_id'];
        $publishers = get_the_terms($post_id, 'publisher');
        $publisher_links = [];

        if (!empty($publishers) && !is_wp_error($publishers)) {
            foreach ($publishers as $publisher) {
                $publisher_name = $publisher->name;
                $publisher_link = get_edit_term_link($publisher->term_id, 'publisher');
                $publisher_links[] = sprintf('<a href="%s">%s</a>', $publisher_link, $publisher_name);
            }
        }

        return implode(', ', $publisher_links);
    }

    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="bulk-delete[]" value="%s" />',
            $item['post_id']
        );
    }

    private function record_count(): int
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'books_info';
        $posts_table = $wpdb->prefix . 'posts';

        $count = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$posts_table} p
            LEFT JOIN {$table_name} bi ON p.ID = bi.post_id
            WHERE p.post_type = 'book' AND p.post_status NOT IN ('auto-draft', 'trash')
        ");

        return (int) $count;
    }

    public function get_books($per_page = 20, $page_number = 1): array|object|null
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'books_info';
        $posts_table = $wpdb->prefix . 'posts';

        $sql = "
            SELECT p.ID AS post_id, COALESCE(bi.isbn, '') AS isbn
            FROM {$posts_table} p
            LEFT JOIN {$table_name} bi ON p.ID = bi.post_id
            WHERE p.post_type = 'book' AND p.post_status NOT IN ('auto-draft', 'trash')
        ";

        if (!empty($_REQUEST['orderby'])) {
            $orderby = esc_sql($_REQUEST['orderby']);
            if (in_array($orderby, ['title', 'isbn', 'date'])) {
                $sql .= ' ORDER BY ' . $orderby;
            }
            $sql .= !empty($_REQUEST['order']) ? ' ' . esc_sql($_REQUEST['order']) : ' ASC';
        }

        $sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', $this->get_items_per_page('books_per_page'), ($this->get_pagenum() - 1) * $this->get_items_per_page('books_per_page'));

        return $wpdb->get_results($sql, 'ARRAY_A');
    }

    public function get_bulk_actions(): array
    {
        return [
            'bulk-delete' => __('Delete', 'books-plugin'),
        ];
    }

    public function process_bulk_action(): void
    {
        // Check for nonce security
        if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_book_' . $_GET['book'])) {
            if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['book'])) {
                $book_id = absint($_GET['book']);
                $this->delete_book($book_id);
                // Redirect after deletion to avoid resubmission on refresh
                wp_redirect(add_query_arg(['page' => 'books-list'], admin_url('admin.php')));
                exit;
            }
        }

        // Check for bulk actions
        if ((isset($_POST['action']) && $_POST['action'] === 'bulk-delete') ||
            (isset($_POST['action2']) && $_POST['action2'] === 'bulk-delete')) {
            $delete_ids = isset($_POST['bulk-delete']) ? array_map('absint', $_POST['bulk-delete']) : [];

            foreach ($delete_ids as $id) {
                $this->delete_book($id);
            }

            // Redirect after bulk delete
            wp_redirect(add_query_arg(['page' => 'books-list'], admin_url('admin.php')));
            exit;
        }
    }


    public function delete_book($id): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'books_info';

        $wpdb->delete(
            $table_name,
            ['post_id' => $id],
            ['%d']
        );

        wp_delete_post($id, true);
    }
}