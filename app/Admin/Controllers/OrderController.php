<?php

namespace App\Admin\Controllers;

use App\Orders;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;
use Encore\Admin\Widgets\InfoBox;
use Symfony\Component\HttpFoundation\Request;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Row;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Chart\Line;

class OrderController extends Controller
{
    use ModelForm;
    
    /**
     * Index interface.
     *
     * @return Content
     */
    public function index(Request $request)
    {
        $created_at_start = $request->query('created_at')['start'] ? NULL : "1970-01-01 00:00:00";
        $created_at_end   = $request->query('created_at')['end'] ? NULL : date("Y-m-d H:i:s", time());
        $sku              = $request->query('sku') ? NULL : "all";
        
        if($sku == "all") {
        
        }
        
        
        $data = [
            "labels"   => ["January", "February", "March", "April", "May", "June", "July"],
            "datasets" => [
                [
                    "label"                => "My First dataset",
                    "fillColor"            => "rgba(0,255,0,0.2)",
                    "strokeColor"          => "rgba(220,220,220,1)",
                    "pointColor"           => "green",
                    "pointStrokeColor"     => "#fff",
                    "pointHighlightFill"   => "#fff",
                    "pointHighlightStroke" => "rgba(220,220,220,1)",
                    "data"                 => [55, 59, 190, 81, 56, 55, 40],
                ],
            ],
        ];
        
        return Admin::content(
            function (Content $content) use ($data) {
                
                $content->header('订单');
                $content->description('订单签收列表');
    
                $content->row(function ($row) {
                    
                    $order_model = new Orders();
                    $total_info = $order_model->totalDeliveryStat();
                    
                    $total_orders = $total_info[0] + $total_info[1] + $total_info[2] + $total_info[3];
                    
                    $total_rate = $total_info[1] / $total_orders * 100 . "%";
                    
                    $box1 = new Box('Total Orders', $total_orders);
                    $box1->style("info");
                    $box2 = new Box('签收', $total_info[1]);
                    $box2->style("info");
                    $box3 = new Box('拒签', $total_info[3]);
                    $box3->style("info");
                    $box4 = new Box('其他(配送中 or 滞留)', $total_info[0] + $total_info[2]);
                    $box4->style("info");
                    
                    $box5 = new Box('总签收率', $total_rate);
                    $box5->style("danger");
                    $row->column(2, $box1);
                    $row->column(2, $box2);
                    $row->column(2, $box3);
                    $row->column(2, $box4);
                    $row->column(2, $box5);
                });
                $content->body($this->grid());
                
                $content->row(
                    function (Row $row) use ($data) {
                        
                        $row->column(
                            12,
                            function (Column $column) use ($data) {
                                
                                $column->append((new Box('签收率', new Line($data)))->style('danger'));
                            }
                        );
                        
                    }
                );
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
                
                $content->header('header');
                $content->description('description');
                
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
            Orders::class,
            function (Grid $grid) {
                
                $grid->id('ID')->sortable();
                $grid->domains("订单域名");
                $grid->sku();
                $grid->num("数量");
                $grid->order_id("订单号");
                $grid->delivery_status("订单物流状态")->display(
                    function ($delivery_status) {
                        if ($delivery_status == 0) {
                            return "暂无物流状态";
                        }
                        if ($delivery_status == 1) {
                            return "签收";
                        }
                        if ($delivery_status == 2) {
                            return "滞留";
                        }
                        if ($delivery_status == 3) {
                            return "拒签";
                        }
                        if ($delivery_status == 4) {
                            return "派送中";
                        }
                    }
                );
                $grid->tracking_no("快递号");
                $grid->company_name("快递公司");
                $grid->last_step("最新物流信息");
                $grid->last_step_time("物流信息当地时间");
                
                $grid->filter(
                    function ($filter) {
                        
                        $filter->disableIdFilter();
                        
                        $filter->equal('sku', 'SKU')->select(
                            [
                                "telescope300" => "telescope300",
                                "telescope200" => "telescope200",
                            ]
                        );
                        $filter->between('created_at', "发货时间")->datetime();
                        
                    }
                );
                
                $grid->disableActions();
                
                $grid->disableCreation();
                
                $grid->created_at();
                $grid->updated_at();
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
            Orders::class,
            function (Form $form) {
                
                $form->file('file_column', '选择文件');
                // callback after save
                
                //$form->ignore('file_cloumn');
                
                $form->saved(
                    function (Form $form) {
                        
                        return redirect('order_insert');
                        
                    }
                );
                
                $form->saving(
                    function (Form $form) {
                    }
                );
                
                $form->disableReset();
                
                $form->setAction('order_insert');
                
            }
        );
    }
    
}
