<?php

namespace App\Admin\Controllers;

use App\Purchases;
use App\Products;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;

class PurchasesController extends Controller
{
    use ModelForm;

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        return Admin::content(function (Content $content) {

            $content->header('采购');
            $content->description('新建采购记录');

            $content->body($this->grid());
        });
    }

    /**
     * Edit interface.
     *
     * @param $id
     * @return Content
     */
    public function edit($id)
    {
        return Admin::content(function (Content $content) use ($id) {

            $content->header('header');
            $content->description('description');

            $content->body($this->form()->edit($id));
        });
    }

    /**
     * Create interface.
     *
     * @return Content
     */
    public function create()
    {
        return Admin::content(function (Content $content) {

            $content->header('header');
            $content->description('description');

            $content->body($this->form());
        });
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Admin::grid(Purchases::class, function (Grid $grid) {

            $grid->id('ID')->sortable();
            
            $grid->model()->orderBy("is_delivered", "asc");
            
            $grid->column("sku", "SKU")->display(
                function ($sku) {
                    $ret = Products::where('id', $sku)->get(['sku'])->first();
            
                    return $ret['sku'];
            
                }
            );
            
            $grid->publisher("录入人");
            $grid->delivery_company("快递公司");
            $grid->delivery_no("快递号");
            $grid->count("订货数量");
            $grid->is_delivered("是否到货")->display(
                function($is_delivered) {
                    return $is_delivered ? "到货" : "未到货";
                }
            );
    
            $grid->disableCreation();
            $grid->disableExport();
            $grid->disableRowSelector();
            $grid->disableActions();
            $grid->created_at();
            $grid->updated_at();
            
            $grid->filter(
                function ($filter) {
            
                    $filter->useModal();
                    $filter->disableIdFilter();
                    $filter->equal('sku', 'SKU')->select("/api/sku");
                    //$filter->equal('type', '类型')->select(['0' => '出库', '1' => '入库']);
                }
            );
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Admin::form(Purchases::class, function (Form $form) {
    
            $sku_id = isset($_GET['sku_id']) ? $_GET['sku_id'] : 0;
            
            $form->display('id', 'ID');
            $form->hidden('sku', "SKU")->default($sku_id);
            // type 1 表示入库 0 表示出库
            $form->hidden('publisher', "操作人")->default(Admin::user()->name);
            $form->hidden('is_delivered')->default(0);
    
    
            $form->select("delivery_company", "物流公司")->options(
                [
                    "顺丰" => '顺丰',
                    "圆通" => '圆通',
                    '中通' => '中通',
                    '申通' => '申通',
                    '汇通' => '汇通',
                    '韵达' => '韵达',
                ]
            );
    
            $form->text("delivery_no", "快递单号")->rules('required');
            $form->text("count", "入库数量")->rules('required|numeric');
    
            $form->tools(function (Form\Tools $tools) {
        
                // Disable back btn.
                //$tools->disableBackButton();
        
                // Disable list btn
                $tools->disableListButton();
        
            });
    
            $form->display('created_at', 'Created At');
            $form->display('updated_at', 'Updated At');
        });
    }
}
