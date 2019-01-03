Imports AutoUpdaterDotNET
Public Class frmControlpanel
    Protected Overrides ReadOnly Property CreateParams() As CreateParams
        Get
            Dim param As CreateParams = MyBase.CreateParams
            param.ClassStyle = param.ClassStyle Or &H200
            Return param
        End Get
    End Property
    Private Sub barButtonItem4_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles barButtonItem4.ItemClick
        frmUser.Show()
        frmUser.MdiParent = Me
    End Sub

    Private Sub BarButtonItem11_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles BarButtonItem11.ItemClick
        frmgenbill.Show()
        frmgenbill.MdiParent = Me
    End Sub

    Private Sub BarButtonItem12_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles BarButtonItem12.ItemClick
        frmgenor.Show()
        frmgenor.MdiParent = Me
    End Sub

    Private Sub BarButtonItem18_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles BarButtonItem18.ItemClick
        frmbilltrans.Show()
        frmbilltrans.MdiParent = Me
    End Sub

    Private Sub BarButtonItem19_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles BarButtonItem19.ItemClick
        frmortrans.Show()
        frmortrans.MdiParent = Me
    End Sub

    Private Sub BarButtonItem20_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles BarButtonItem20.ItemClick
        frmclient.Show()
        frmclient.MdiParent = Me
    End Sub

    Private Sub BarButtonItem22_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles BarButtonItem22.ItemClick
        frmservice.Show()
        frmservice.MdiParent = Me
    End Sub

    Private Sub BarButtonItem21_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles BarButtonItem21.ItemClick
        frmtariff.Show()
        frmtariff.MdiParent = Me
    End Sub

    Private Sub BarButtonItem13_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles BarButtonItem13.ItemClick
        repVolume.ShowDialog()
    End Sub

    Private Sub Button1_Click(sender As Object, e As EventArgs)
        frmVolume.ShowDialog()
    End Sub

    Private Sub BarButtonItem14_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles BarButtonItem14.ItemClick
        repRevenue.ShowDialog()
    End Sub

    Private Sub barButtonItem10_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles barButtonItem10.ItemClick
        repBill.ShowDialog()
    End Sub

    Private Sub barButtonItem8_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles barButtonItem8.ItemClick
        repOr.ShowDialog()
    End Sub

    Private Sub frmControlpanel_Load(sender As Object, e As EventArgs) Handles MyBase.Load

    End Sub

    Private Sub BarButtonItem15_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles BarButtonItem15.ItemClick
        frmPercent.Show()
        frmPercent.MdiParent = Me
    End Sub

    Private Sub Timer1_Tick(sender As Object, e As EventArgs) Handles Timer1.Tick
        systemtime(BarEditItem3.EditValue)
        systemtime(sysdate)
    End Sub

    Private Sub BarButtonItem23_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles BarButtonItem23.ItemClick
        frmReqDate.Show()
        frmReqDate.MdiParent = Me
    End Sub

    Private Sub barButtonItem9_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles barButtonItem9.ItemClick
        systemtime(sysdate)
        Dim result As Integer = MessageBox.Show("Are you sure you want to Sign out?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            singinlogs("Login", BarEditItem2.EditValue + " Has signed out | Time: " + sysdate)

            With frmLogin
                .txtUname.Text = "Username"
                .txtPass.Text = "Password"
                .txtUname.Focus()
            End With

            Me.Dispose()
            frmLogin.Show()
        End If
    End Sub

    Private Sub BarButtonItem16_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles BarButtonItem16.ItemClick
        frmloginaudit.Show()
        frmloginaudit.MdiParent = Me
    End Sub

    Private Sub BarButtonItem17_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles BarButtonItem17.ItemClick
        frmtransaudit.Show()
        frmtransaudit.MdiParent = Me
    End Sub

    Private Sub BarButtonItem24_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles BarButtonItem24.ItemClick
        frmnumberaudit.Show()
        frmnumberaudit.MdiParent = Me
    End Sub

    Private Sub barButtonItem3_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles barButtonItem3.ItemClick
        About.ShowDialog()
    End Sub

    Private Sub barButtonItem2_ItemClick(sender As Object, e As DevExpress.XtraBars.ItemClickEventArgs) Handles barButtonItem2.ItemClick
        AutoUpdater.Start("http://192.168.10.29/update/update.xml")
    End Sub
End Class