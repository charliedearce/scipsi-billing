Public Class frmTransDelBill
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub frmTransDelBill_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Back) Then
            Dim result As Integer = MessageBox.Show("Are you sure you want to delete this Bill " + txtbillno.Text + "?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                DelYtranstItem()
                DeleteYmitalBill()
                ViewYTrans()
            End If
        ElseIf (e.KeyCode = Keys.Escape) Then
            frmYtrans.txtNotice.Text = ""
            Me.Dispose()
        End If
    End Sub

    Private Sub frmTransDelBill_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
        DeleteYmitalBill()
    End Sub

    Private Sub dgYtransDel_CellContentClick(sender As Object, e As DataGridViewCellEventArgs) Handles dgYtransDel.CellContentClick

    End Sub

    Private Sub dgYtransDel_SelectionChanged(sender As Object, e As EventArgs) Handles dgYtransDel.SelectionChanged
        On Error Resume Next
        txtItemid.Text = dgYtransDel.CurrentRow.Cells(0).Value.ToString
        txtbillno.Text = dgYtransDel.CurrentRow.Cells(1).Value.ToString
    End Sub

End Class