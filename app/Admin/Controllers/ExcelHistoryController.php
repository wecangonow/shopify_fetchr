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
                $grid->domain_name('域名');
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
                
                $form->select("domain_name", "选择站点域名")->options($domains);
                $form->hidden('publisher', "操作人")->default(Admin::user()->name);
                $form->file('file_path', '选择文件');
                
                $form->display('created_at', 'Created At');
                $form->display('updated_at', 'Updated At');
                
                $form->saved(
                    function (Form $form) {
                        
                        $excel_path = public_path() . "/uploads/" . $form->model()->file_path;
                        
                        
                        Excel::load($excel_path,function($reader) use ($form){
                            // Loop through all sheets
                            $reader->each(function($sheet) use ($form) {
        
                                // Loop through all rows
                                $sheet->each(function($row) use ($form) {
                                    
                                    $model = new Orders();
                                    $info = $row->toArray();
                                    $order_id = $info['order_id'];
                                    $delivery_no = $info['delivery_no'];
                                    $sku = $info['sku'];
                                    $delivery_company = $info['delivery_company'];
                                    $num = $info['num'];
                                    
                                    $model->sku = $sku;
                                    $model->order_id = $order_id;
                                    $model->company_name = $delivery_company;
                                    $model->tracking_no = $delivery_no;
                                    $model->num = $num;
                                    $model->domains = $form->model()->domain_name;
                                    
                                    $model->save();
                                    
                                    Log::info("Info: " . $order_id . " -" . $delivery_company . "-" . $delivery_no . "\n");
            
                                });
                                
        
                            });
                            
                            die;
    
                        });
    
                        
                    }
                );
                
            }
        );
    }
}
