<?php

namespace App\Admin\Controllers;

use App\Orders;
use function foo\func;
use Maatwebsite\Excel\Facades\Excel;
use App\ExcelHistory;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;
use Illuminate\Support\Facades\Log;
use App\InventoryHistory;
use App\Products;
use Illuminate\Support\Facades\DB;

class ExcelHistoryController extends Controller
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
                
                $content->header('header');
                $content->description('description');
                
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
                
                $content->header('excel文件');
                $content->description('上传记录');
                
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
            ExcelHistory::class,
            function (Grid $grid) {
                
                $grid->id('ID')->sortable();
                $grid->publisher('上传人');
                $grid->file_path('文件路径');
                $grid->created_at();
                $grid->updated_at();
                
                $grid->disableExport();
                $grid->disableActions();
                $grid->disableFilter();
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
            ExcelHistory::class,
            function (Form $form) {
                
                $form->display('id', 'ID');
                
                $domains = config('app.domains');
                
                $form->hidden('publisher', "操作人")->default(Admin::user()->name);
                $form->file('file_path', '选择文件');
                
                $form->display('created_at', 'Created At');
                $form->display('updated_at', 'Updated At');
                
                $form->saved(
                    function (Form $form) {
                        
                        $excel_path = public_path() . "/uploads/" . $form->model()->file_path;
                        
                        Excel::load(
                            $excel_path,
                            function ($reader) use ($form) {
                                // Loop through all sheets
                                
                                $reader = $reader->getSheet(0);
                                
                                $data = $reader->toArray();
                                unset($data[0]);
                                
                                if (count($data) > 0) {
                                    foreach ($data as $v) {
                                        if ($v[0]) {
                                            
                                            $model            = new Orders();
                                            $client_ref       = $v[0];
                                            $tracking_no      = $v[1];
                                            $sku              = $v[2];
                                            $delivery_company = "fetchr";
                                            $num              = $v[3];
                                            
                                            
                                            $model->sku          = $sku;
                                            $model->client_ref   = $client_ref;
                                            $model->company_name = $delivery_company;
                                            $model->tracking_no  = $tracking_no;
                                            $model->num          = $num;
                                            
                                            $model->save();
                                            
                                            Log::info(
                                                "Info: "
                                                . $client_ref
                                                . " -"
                                                . $delivery_company
                                                . "-"
                                                . $tracking_no
                                                . "\n"
                                            );
                                            
                                            $model = new InventoryHistory();
                                            
                                            $sku_id = Products::where("sku", $sku)->get(['id'])->first()['id'];
                                            
                                            if ($sku_id) {
                                                $model->username        = Admin::user()->name;
                                                $model->deliver_company = "fetchr";
                                                $model->deliver_number  = $tracking_no;
                                                $model->quantity        = $num;
                                                $model->type            = 0;   //  0 出库  1  入库
                                                $model->sku_id          = $sku_id;
                                                $model->warehouse       = "广州";
                                                
                                                if ($model->save()) {
                                                    Products::where('sku', $sku)->update(
                                                        ['shenzhen_inventory' => DB::raw('shenzhen_inventory-' . $num)]
                                                    );
                                                    
                                                    $message = sprintf("SKU is %s 广州 warehouse reduce %d", $sku, $num);
                                                    
                                                    Log::info($message);
                                                    
                                                }
                                                
                                            }
                                            
                                        }
                                    }
                                    
                                }
                                
                            }
                        );
                        
                    }
                );
                
            }
        );
    }
}
