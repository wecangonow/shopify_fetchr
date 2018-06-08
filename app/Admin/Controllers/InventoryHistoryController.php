<?php

namespace App\Admin\Controllers;

use App\InventoryHistory;

use App\Purchases;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Products;
use Illuminate\Support\Facades\DB;

class InventoryHistoryController extends Controller
{
    use ModelForm;

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index(Request $request)
    {
        return Admin::content(
            function (Content $content) {

                $content->header('出入库信息列表');
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

                $content->header('header');
                $content->description('description');

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

                $content->header('添加');
                $content->description('库存记录');

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
            InventoryHistory::class,
            function (Grid $grid) {

                $grid->model()->orderBy("created_at", "desc");

                $grid->id('ID')->sortable();

                $grid->column("sku_id", "SKU")->display(
                    function ($sku_id) {
                        $ret = Products::where('id', $sku_id)->get(['sku'])->first();

                        return $ret['sku'];

                    }
                );

                $grid->warehouse("仓库");

                $grid->column("type", "类型")->display(
                    function ($type) {
                        return $type ? "入库" : "出库";

                    }
                )->sortable();
                $grid->quantity("数量");
                $grid->deliver_company("物流公司");
                $grid->deliver_number("快递单号");

                $grid->username("操作员");
                $grid->created_at("创建时间")->sortable();
                $grid->updated_at("更新时间");

                $grid->disableCreation();
                $grid->disableExport();
                $grid->disableRowSelector();
                $grid->disableActions();

                $grid->filter(
                    function ($filter) {

                        //$filter->useModal();
                        //$filter->disableIdFilter();
                        $filter->equal('sku_id', 'SKU')->select("/api/sku");
                        $filter->equal('type', '类型')->select(['0' => '出库', '1' => '入库']);
                    }
                );

                $grid->actions(
                    function ($actions) {
                        $actions->disableDelete();
                        $actions->disableEdit();
                    }
                );
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
            InventoryHistory::class,
            function (Form $form) {

                $sku_id = isset($_GET['sku_id']) ? $_GET['sku_id'] : 0;

                $form->display('id', 'ID');

                $form->hidden('sku_id', "SKU_ID")->default($sku_id);
                // type 1 表示入库 0 表示出库
                $form->hidden('type', "操作类型")->default(1);
                $form->hidden('username', "操作人")->default(Admin::user()->name);

                $form->select("warehouse", "仓库")->options(
                    [
                        "guangzhou" => 'guangzhou',
                        "saudi"     => 'saudi',
                    ]
                );

                $form->select("deliver_company", "物流公司")->options(
                    [
                        "顺丰" => '顺丰',
                        "圆通" => '圆通',
                        '中通' => '中通',
                        '申通' => '申通',
                        '汇通' => '汇通',
                        '韵达' => '韵达',
                    ]
                );

                $form->text("deliver_number", "快递单号")->rules('required');
                $form->text("quantity", "入库数量")->rules('required|numeric');
                $form->display('created_at', 'Created At');
                $form->display('updated_at', 'Updated At');

                $form->disableReset();

                $form->saved(
                    function (Form $form) {

                        if ($form->warehouse == "guangzhou") {
                            $ret = Products::where('id', $form->sku_id)->update(
                                ['shenzhen_inventory' => DB::raw('shenzhen_inventory+' . $form->quantity)]
                            );
                        }
                        elseif ($form->warehouse == "saudi") {
                            $ret = Products::where('id', $form->sku_id)->update(
                                ['saudi_inventory' => DB::raw('saudi_inventory+' . $form->quantity)]
                            );
                        }

                        $sku_name = Products::where('id', $form->sku_id)->get(['sku'])->first()['sku'];

                        if ($ret == 1) {
                            Log::info(
                                "SKU: " . $sku_name . " inventory added $form->quantity by $form->username success"
                            );
                        }
                        else {

                            Log::info(
                                "SKU: " . $sku_name . " inventory added $form->quantity by $form->username failed"
                            );
                        }
                        
                        $delivery_number = $form->deliver_number;
                        
                        Purchases::where("delivery_no", $delivery_number)->update(['is_delivered' => 1, 'updated_at' => date("Y-m-d H:i:s", time())]);

                        return redirect("admin/products");
                    }
                );
            }
        );
    }
}
