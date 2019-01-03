Public Class frmWtrans
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub btnAddBill_Click(sender As Object, e As EventArgs) Handles btnAddOr.Click
        If txtWtransno.Text = "" Then
            txtNotice.Text = "Please fill up the important fields."
        Else
            frmWtransOrno.ShowDialog()
        End If
    End Sub

    Private Sub btnDelOr_Click(sender As Object, e As EventArgs) Handles btnDelOr.Click
        frmWTransDelor.showdialog()
    End Sub

    Private Sub btnClose_Click(sender As Object, e As EventArgs) Handles btnClose.Click
        Me.Dispose()
    End Sub

    Private Sub btnPrintTrans_Click(sender As Object, e As EventArgs) Handles btnPrintTrans.Click
        If txtWtransno.Text = "" Then
            txtNotice.Text = "Please fill up the important fields."
        Else
            If txtindi1.Text = 0 Then
                SaveWmital()
                frmPrintWtrans.ShowDialog()
                txtWtransno.Enabled = False
                txtBilldate.Enabled = False
                btnAddOr.Enabled = False
                btnDelOr.Enabled = False
                btnDelTrans.Enabled = False
                btnSearchTrans.Enabled = True
                btnNewTrans.Enabled = True
                btnClose.Enabled = True

            Else
                UpdateWmital()
                txtWtransno.Enabled = False
                txtBilldate.Enabled = False
                btnAddOr.Enabled = False
                btnDelOr.Enabled = False
                btnDelTrans.Enabled = False
                btnSearchTrans.Enabled = True
                btnNewTrans.Enabled = True
                btnClose.Enabled = True
                btnEdit.Enabled = True
                frmPrintWtrans.ShowDialog()
            End If
        End If
    End Sub

    Private Sub btnNewTrans_Click(sender As Object, e As EventArgs) Handles btnNewTrans.Click
        btnDelTrans.Enabled = False
        btnNewTrans.Enabled = False
        btnEdit.Enabled = False
        txtWtransno.Enabled = True
        txtBilldate.Enabled = True
        txtWtransno.Text = ""
        txtBilldate.Text = ""
        txtindi.Text = 1
        txtindi1.Text = 0
        ViewWTrans()
        txtWtransno.Focus()
        txtNotice.Text = ""
    End Sub

    Private Sub btnDelTrans_Click(sender As Object, e As EventArgs) Handles btnDelTrans.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to delete this Transmittal #" + txtWtransno.Text + " ?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            WtransDel()
            ViewWTrans()
        End If
    End Sub

    Private Sub btnSearchTrans_Click(sender As Object, e As EventArgs) Handles btnSearchTrans.Click
        txtindi.Text = 0
        frmSearchWmital.ShowDialog()
    End Sub

    Private Sub btnEdit_Click(sender As Object, e As EventArgs) Handles btnEdit.Click
        txtBilldate.Enabled = True
        btnAddOr.Enabled = True
        btnDelOr.Enabled = True
        btnDelTrans.Enabled = False
        btnNewTrans.Enabled = False
        btnEdit.Enabled = False
        txtindi.Text = 1
        txtindi1.Text = 1
    End Sub

    Private Sub frmWtrans_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.F1) Then
            If btnNewTrans.Enabled = True Then
                btnNewTrans_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F2) Then
            If btnAddOr.Enabled = True Then
                btnAddBill_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F3) Then
            If btnDelOr.Enabled = True Then
                btnDelOr_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F4) Then
            If btnPrintTrans.Enabled = True Then
                btnPrintTrans_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F5) Then
            If btnDelTrans.Enabled = True Then
                btnDelTrans_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F6) Then
            If btnSearchTrans.Enabled = True Then
                btnSearchTrans_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F7) Then
            If btnEdit.Enabled = True Then
                btnEdit_Click(sender, e)
            End If
        End If
    End Sub

    Private Sub frmWtrans_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
    End Sub

    Private Sub dgWtransmittal_CellContentClick(sender As Object, e As DataGridViewCellEventArgs) Handles dgWtransmittal.CellContentClick

    End Sub

    Private Sub dgWtransmittal_CellDoubleClick(sender As Object, e As DataGridViewCellEventArgs) Handles dgWtransmittal.CellDoubleClick
        If txtindi.Text = 0 Then
        Else

            frmedithtax.lblbillno.Text = dgWtransmittal.CurrentRow.Cells(5).Value.ToString
            frmedithtax.txtHtax.Text = dgWtransmittal.CurrentRow.Cells(4).Value.ToString
            frmedithtax.ShowDialog()
        End If
    End Sub
End Class