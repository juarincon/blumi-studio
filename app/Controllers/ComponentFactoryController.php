<?php
namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Middleware\Auth;
use App\Services\ComponentFactoryService;
use App\Services\ComponentLibraryService;
use App\Services\ComponentService;

final class ComponentFactoryController
{
    public function index(): void
    {
        Auth::requireRole(['superadmin','builder']);
        $s=new ComponentFactoryService();
        $libraryId=max(0,(int)($_GET['library_id']??0));
        $categoryId=max(0,(int)($_GET['category_id']??0));
        if($libraryId>0){
            $library=(new ComponentLibraryService())->find($libraryId);
            View::render('components/index',[
                'mode'=>'library','selectedLibrary'=>$library,'libraries'=>$s->libraries(),'components'=>$s->library($libraryId,$categoryId ?: null),
                'categories'=>$s->categories(),'categoryCounts'=>$s->categoryCounts($libraryId),'selectedCategoryId'=>$categoryId,'projects'=>$s->projects()
            ]);
            return;
        }
        View::render('components/index',[
            'mode'=>'libraries','libraries'=>$s->libraries(),'components'=>[],'categories'=>$s->categories(),'categoryCounts'=>[],'selectedCategoryId'=>0,'projects'=>$s->projects()
        ]);
    }

    public function create(): void
    {
        Auth::requireRole(['superadmin','builder']);
        $s=new ComponentFactoryService();
        $selected=max(0,(int)($_GET['library_id']??0));
        $libraries=$s->libraries();
        $selectedLibrary=null;
        if($selected>0){
            foreach($libraries as $library){
                if((int)$library['id']===$selected){ $selectedLibrary=$library; break; }
            }
        }
        View::render('components/create',[
            'categories'=>$s->categories(),
            'libraries'=>$libraries,
            'selectedLibraryId'=>$selected,
            'selectedLibrary'=>$selectedLibrary,
        ]);
    }

    public function createLibrary(): void
    {
        Auth::requireRole(['superadmin','builder']); $this->csrf();
        try{
            $projectId=(int)($_POST['project_id']??0);
            $id=(new ComponentLibraryService())->create(trim((string)($_POST['name']??'')),$projectId>0?$projectId:null,(int)$_SESSION['user']['id']);
            $_SESSION['flash_success']='Librería creada.';
            header('Location: index.php?route=component_factory&library_id='.$id); exit;
        }catch(\Throwable $e){ $_SESSION['flash_error']=$e->getMessage(); header('Location: index.php?route=component_factory'); exit; }
    }

    public function createLibraryInline(): void
    {
        Auth::requireRole(['superadmin','builder']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_csrf'] ?? null)) {
            $this->json(['ok'=>false,'error'=>'La solicitud expiró. Recarga el Canvas e inténtalo nuevamente.'], 419);
        }
        try {
            $projectId=(int)($_POST['project_id']??0);
            if($projectId<=0) throw new \InvalidArgumentException('No se pudo identificar el proyecto actual.');
            $service=new ComponentLibraryService();
            $id=$service->create(trim((string)($_POST['name']??'')),$projectId,(int)$_SESSION['user']['id']);
            $library=$service->find($id);
            $this->json(['ok'=>true,'library'=>['id'=>(int)$library['id'],'name'=>(string)$library['nombre']]]);
        } catch(\InvalidArgumentException $e) {
            $this->json(['ok'=>false,'error'=>$e->getMessage()],422);
        } catch(\Throwable $e) {
            error_log($e->__toString());
            $this->json(['ok'=>false,'error'=>'No se pudo crear la librería.'],500);
        }
    }

    public function generate(): void
    {
        Auth::requireRole(['superadmin','builder']); $this->csrf();
        try {
            $id=(new ComponentFactoryService())->generate(
                $_FILES['reference']??[],
                (int)($_POST['library_id']??0),
                (int)($_POST['category_id']??0),
                trim((string)($_POST['component_name']??'')),
                trim((string)($_POST['instruction']??'')),
                (int)$_SESSION['user']['id']
            );
            $_SESSION['flash_success']='Componente generado. Revísalo antes de aprobarlo.';
            $canvasPageId=max(0,(int)($_POST['canvas_page_id']??0));
            $url='index.php?route=component_factory.show&id='.$id;
            if($canvasPageId>0) $url.='&canvas_page_id='.$canvasPageId;
            header('Location: '.$url); exit;
        } catch(\Throwable $e){
            error_log($e->__toString()); $_SESSION['flash_error']=$e->getMessage();
            $lib=(int)($_POST['library_id']??0);
            $canvasPageId=max(0,(int)($_POST['canvas_page_id']??0));
            if($canvasPageId>0){ header('Location: index.php?route=builder&page_id='.$canvasPageId.'&open_component_creator=1'); exit; }
            header('Location: index.php?route=component_factory.create'.($lib?'&library_id='.$lib:'')); exit;
        }
    }

    public function show(): void
    {
        Auth::requireRole(['superadmin','builder']);
        $s=new ComponentFactoryService(); $c=$s->find((int)($_GET['id']??0));
        $canvasPageId=max(0,(int)($_GET['canvas_page_id']??0));
        View::render('components/show',['component'=>$c,'preview'=>$s->previewHtml($c),'libraries'=>$s->libraries(),'categories'=>$s->categories(),'canvasPageId'=>$canvasPageId]);
    }

    public function saveCss(): void
    {
        Auth::requireRole(['superadmin','builder']); $this->csrf();
        $id=(int)($_POST['id']??0);
        try{
            (new ComponentFactoryService())->saveCssDraft($id,(string)($_POST['css']??''),(int)$_SESSION['user']['id']);
            $_SESSION['flash_success']='CSS guardado. Las clases del componente se mantuvieron intactas.';
        }catch(\Throwable $e){
            $_SESSION['flash_error']=$e->getMessage();
        }
        $url='index.php?route=component_factory.show&id='.$id;
        $canvasPageId=max(0,(int)($_POST['canvas_page_id']??0));
        if($canvasPageId>0) $url.='&canvas_page_id='.$canvasPageId;
        header('Location: '.$url.'#component-css-editor'); exit;
    }

    public function approve(): void
    {
        Auth::requireRole(['superadmin','builder']); $this->csrf(); $id=(int)($_POST['id']??0);
        $canvasPageId=max(0,(int)($_POST['canvas_page_id']??0));
        try{
            (new ComponentFactoryService())->approve($id,(int)($_POST['library_id']??0),(int)($_POST['category_id']??0),trim((string)($_POST['name']??'')),(int)$_SESSION['user']['id']);
            if($canvasPageId>0){
                $instanceId=(new ComponentService())->addToPage($canvasPageId,$id,(int)$_SESSION['user']['id']);
                $_SESSION['flash_success']='Componente aprobado, guardado en la librería e insertado en esta página.';
                header('Location: index.php?route=builder&page_id='.$canvasPageId.'&instance_id='.$instanceId.'#blumi-section-'.$instanceId); exit;
            }
            $_SESSION['flash_success']='Componente aprobado y archivado en su librería.';
        }catch(\Throwable $e){$_SESSION['flash_error']=$e->getMessage();}
        $url='index.php?route=component_factory.show&id='.$id;
        if($canvasPageId>0) $url.='&canvas_page_id='.$canvasPageId;
        header('Location: '.$url); exit;
    }

    public function duplicate(): void
    {
        Auth::requireRole(['superadmin','builder']); $this->csrf(); $source=(int)($_POST['id']??0);
        $canvasPageId=max(0,(int)($_POST['canvas_page_id']??0));
        $ctx=$canvasPageId>0?'&canvas_page_id='.$canvasPageId:'';
        try{$id=(new ComponentFactoryService())->duplicateWithPrompt($source,trim((string)($_POST['instruction']??'')),(int)$_SESSION['user']['id']); $_SESSION['flash_success']='Variante creada. Revisa el resultado.'; header('Location: index.php?route=component_factory.show&id='.$id.$ctx); exit;}
        catch(\Throwable $e){$_SESSION['flash_error']=$e->getMessage(); header('Location: index.php?route=component_factory.show&id='.$source.$ctx); exit;}
    }

    public function preview(): void
    {
        Auth::requireRole(['superadmin','builder']); $s=new ComponentFactoryService(); $c=$s->find((int)($_GET['id']??0)); header('Content-Type: text/html; charset=utf-8'); echo $s->previewHtml($c);
    }

    private function json(array $payload, int $status=200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function csrf(): void { if($_SERVER['REQUEST_METHOD']!=='POST'||!Csrf::validate($_POST['_csrf']??null)){http_response_code(419);exit('Solicitud expirada.');} }
}
