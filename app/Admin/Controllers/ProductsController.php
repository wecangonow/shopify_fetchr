<?php

namespace App\Admin\Controllers;

use App\Products;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;

class ProductsController extends Controller
{
    use ModelForm;

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        return Admin::content(
            function (Content $content) {

                $content->header('SKU列表');
                $content->description('');

                $content->body($this->grid());
            }
        );
    }

    /**
     * Edit interface.
     *
     * @param $id
     *
     * @return Content
     */
    public function edit($id)
    {
        return Admin::content(
            function (Content $content) use ($id) {

                $content->header('编辑SKU');
                $content->description('');

                $content->body($this->form()->edit($id));
            }
        );
    }

    /**
     * Create interface.
     *
     * @return Content
     */
    public function create()
    {
        return Admin::content(
            function (Content $content) {

                $content->header('新增SKU');
                $content->description('');

                $content->body($this->form());
            }
        );
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Admin::grid(
            Products::class,
            function (Grid $grid) {

                $grid->model()->orderBy("updated_at", "desc");

                $grid->id('ID')->sortable();
                $grid->picture()->image('', 60, 60);
                $grid->sku("SKU")->sortable();
                $grid->shenzhen_inventory("广州库存")->sortable();
                $grid->saudi_inventory("沙特库存")->sortable();

                $grid->column("总库存", "总库存")->display(
                    function () {
                        return $this->shenzhen_inventory + $this->saudi_inventory;
                    }
                );

                $grid->disableExport();

                $grid->filter(
                    function ($filter) {

                        //$filter->useModal();
                        //$filter->disableIdFilter();
                        $filter->like('sku', 'SKU');
                    }
                );

                $grid->actions(function($actions) {

                    $key = $actions->getKey();
                    $actions->disableDelete();
                    $actions->disableEdit();
                    $actions->prepend('<a href="inventory_history/create?&sku_id='. $key . '">入库</a>');
                    $actions->prepend('<a href="inventory_history?&sku_id='. $key . '">库存记录&nbsp;&nbsp;</a>');


                });

                $grid->created_at("");
                $grid->updated_at("更新时间")->sortable("desc");
            }
        );
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Admin::form(
            Products::class,
            function (Form $form) {

                $form->display('id', 'ID');
                $form->text("sku", "SKU");
                $form->text("picture", "图片链接");
                $form->number("shenzhen_inventory", "广州库存");
                $form->display("saudi_inventory", "沙特库存");

                $form->display('created_at', 'Created At');
                $form->display('updated_at', 'Updated At');
                $form->disableReset();
            }
        );
    }
}
