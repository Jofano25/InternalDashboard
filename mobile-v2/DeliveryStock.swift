import SwiftUI
import WebKit
import Security

@main struct DeliveryStockApp: App { var body: some Scene { WindowGroup { MobileView() } } }
struct MobileView: UIViewRepresentable {
    func makeCoordinator() -> Bridge { Bridge() }
    func makeUIView(context: Context) -> WKWebView {
        let config = WKWebViewConfiguration()
        config.websiteDataStore = .nonPersistent()
        config.userContentController.add(context.coordinator, name: "device")
        let view = WKWebView(frame: .zero, configuration: config)
        context.coordinator.view = view
        view.navigationDelegate = context.coordinator
        if let url = Bundle.main.url(forResource: "mobile", withExtension: "html") { view.loadFileURL(url, allowingReadAccessTo: url) }
        return view
    }
    func updateUIView(_ uiView: WKWebView, context: Context) {}
    static func dismantleUIView(_ uiView: WKWebView, coordinator: Bridge) { uiView.configuration.userContentController.removeScriptMessageHandler(forName: "device") }
}
final class Bridge: NSObject, WKScriptMessageHandler, WKNavigationDelegate, URLSessionTaskDelegate {
    weak var view: WKWebView?
    let queue = DispatchQueue(label: "delivery.stock.identity")
    let tag = Data("id.co.elmecon.deliverystock.v2.key".utf8)
    func webView(_ webView: WKWebView, decidePolicyFor navigationAction: WKNavigationAction, decisionHandler: @escaping (WKNavigationActionPolicy)->Void){
        decisionHandler(navigationAction.request.url == Bundle.main.url(forResource:"mobile",withExtension:"html") ? .allow : .cancel)
    }
    func urlSession(_ session: URLSession, task: URLSessionTask, willPerformHTTPRedirection response: HTTPURLResponse, newRequest request: URLRequest, completionHandler: @escaping (URLRequest?)->Void){ completionHandler(nil) }
    func random(_ count:Int) throws -> Data { var bytes=[UInt8](repeating:0,count:count);guard SecRandomCopyBytes(kSecRandomDefault,count,&bytes)==errSecSuccess else{throw Failure.invalid};return Data(bytes) }
    enum Failure: Error { case invalid }
    func key() throws -> SecKey {
        let query:[String:Any]=[kSecClass as String:kSecClassKey,kSecAttrApplicationTag as String:tag,kSecAttrKeyType as String:kSecAttrKeyTypeECSECPrimeRandom,kSecReturnRef as String:true]
        var result:CFTypeRef?;let status=SecItemCopyMatching(query as CFDictionary,&result)
        if status==errSecSuccess,let item=result { return item as! SecKey }
        guard status==errSecItemNotFound else{throw Failure.invalid}
        let attributes:[String:Any]=[kSecAttrKeyType as String:kSecAttrKeyTypeECSECPrimeRandom,kSecAttrKeySizeInBits as String:256,kSecPrivateKeyAttrs as String:[kSecAttrIsPermanent as String:true,kSecAttrApplicationTag as String:tag,kSecAttrAccessible as String:kSecAttrAccessibleWhenUnlockedThisDeviceOnly]]
        guard let created=SecKeyCreateRandomKey(attributes as CFDictionary,nil) else{throw Failure.invalid};return created
    }
    func token() throws -> String {
        let query:[String:Any]=[kSecClass as String:kSecClassGenericPassword,kSecAttrService as String:"delivery.stock.v2",kSecAttrAccount as String:"token"]
        var find=query;find[kSecReturnData as String]=true;var result:CFTypeRef?;let status=SecItemCopyMatching(find as CFDictionary,&result)
        if status==errSecSuccess,let data=result as? Data {return data.base64EncodedString()};guard status==errSecItemNotFound else{throw Failure.invalid}
        let bytes=try random(32);var insert=query;insert[kSecValueData as String]=bytes;insert[kSecAttrAccessible as String]=kSecAttrAccessibleWhenUnlockedThisDeviceOnly;guard SecItemAdd(insert as CFDictionary,nil)==errSecSuccess else{throw Failure.invalid};return bytes.base64EncodedString()
    }
    func userContentController(_ userContentController:WKUserContentController,didReceive message:WKScriptMessage){
        guard message.frameInfo.isMainFrame,let raw=message.body as? String,let data=raw.data(using:.utf8),let req=(try? JSONSerialization.jsonObject(with:data)) as? [String:Any],let id=req["id"] as? Int,let action=req["action"] as? String,let fields=req["data"] as? [String:Any],["status","register","stock","admin_list","admin_code","admin_approve"].contains(action) else{return}
        queue.async{do{try self.call(id,action,fields)}catch{self.reply(["id":id,"error":"Gagal terhubung / konfigurasi server belum siap."])}}
    }
    func call(_ id:Int,_ action:String,_ fields:[String:Any]) throws {
        guard let raw=Bundle.main.object(forInfoDictionaryKey:"DeviceAPIURL") as? String,let url=URL(string:raw),url.scheme=="https",url.user==nil else{throw Failure.invalid}
        if !UserDefaults.standard.bool(forKey:"delivery.v2.installed") {
            let keys:[String:Any]=[kSecClass as String:kSecClassKey,kSecAttrApplicationTag as String:tag,kSecAttrKeyType as String:kSecAttrKeyTypeECSECPrimeRandom]
            let tokens:[String:Any]=[kSecClass as String:kSecClassGenericPassword,kSecAttrService as String:"delivery.stock.v2",kSecAttrAccount as String:"token"]
            for item in [keys,tokens] { let status=SecItemDelete(item as CFDictionary); guard status==errSecSuccess || status==errSecItemNotFound else{throw Failure.invalid} }
            UserDefaults.standard.set(true,forKey:"delivery.v2.installed")
        }
        let privateKey=try key();guard let pub=SecKeyCopyPublicKey(privateKey),let x=SecKeyCopyExternalRepresentation(pub,nil) as Data?,x.count==65 else{throw Failure.invalid}
        // P-256 SPKI prefix + uncompressed X9.63 point, compatible with Android and PHP OpenSSL.
        let prefix=Data([0x30,0x59,0x30,0x13,0x06,0x07,0x2a,0x86,0x48,0xce,0x3d,0x02,0x01,0x06,0x08,0x2a,0x86,0x48,0xce,0x3d,0x03,0x01,0x07,0x03,0x42,0x00]);let spki=prefix+x
        let p:[String:Any]=["action":action,"token":try token(),"public_key":spki.base64EncodedString(),"timestamp":Int(Date().timeIntervalSince1970),"nonce":try random(16).base64EncodedString(),"platform":"ios","model":"iPhone / iPad","version":206,"data":fields]
        let encoded=try JSONSerialization.data(withJSONObject:p).base64EncodedString();guard let signature=SecKeyCreateSignature(privateKey,.ecdsaSignatureMessageX962SHA256,Data(encoded.utf8) as CFData,nil) as Data? else{throw Failure.invalid}
        var request=URLRequest(url:url);request.httpMethod="POST";request.timeoutInterval=15;request.setValue("application/json",forHTTPHeaderField:"Content-Type");request.httpBody=try JSONSerialization.data(withJSONObject:["payload":encoded,"signature":signature.base64EncodedString()])
        let session=URLSession(configuration:.ephemeral,delegate:self,delegateQueue:nil)
        session.dataTask(with:request){data,response,error in defer{session.finishTasksAndInvalidate()};guard error==nil,(response as? HTTPURLResponse)?.statusCode==200,let data=data,data.count<=1_000_000,let obj=(try? JSONSerialization.jsonObject(with:data)) as? [String:Any] else{self.reply(["id":id,"error":"Gagal terhubung / konfigurasi server belum siap."]);return};self.reply(["id":id,"data":obj])}.resume()
    }
    func reply(_ obj:[String:Any]) { guard let data=try? JSONSerialization.data(withJSONObject:obj),let json=String(data:data,encoding:.utf8) else{return};DispatchQueue.main.async{self.view?.evaluateJavaScript("receive("+json+")",completionHandler:nil)} }
}
