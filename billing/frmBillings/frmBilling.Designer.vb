<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmBilling
    Inherits System.Windows.Forms.Form

    'Form overrides dispose to clean up the component list.
    <System.Diagnostics.DebuggerNonUserCode()> _
    Protected Overrides Sub Dispose(ByVal disposing As Boolean)
        Try
            If disposing AndAlso components IsNot Nothing Then
                components.Dispose()
            End If
        Finally
            MyBase.Dispose(disposing)
        End Try
    End Sub

    'Required by the Windows Form Designer
    Private components As System.ComponentModel.IContainer

    'NOTE: The following procedure is required by the Windows Form Designer
    'It can be modified using the Windows Form Designer.  
    'Do not modify it using the code editor.
    <System.Diagnostics.DebuggerStepThrough()> _
    Private Sub InitializeComponent()
        Me.components = New System.ComponentModel.Container()
        Dim DataGridViewCellStyle1 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle2 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle3 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle4 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle5 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim resources As System.ComponentModel.ComponentResourceManager = New System.ComponentModel.ComponentResourceManager(GetType(frmBilling))
        Me.GroupBox1 = New System.Windows.Forms.GroupBox()
        Me.btnUpdate = New System.Windows.Forms.Button()
        Me.btnEdit = New System.Windows.Forms.Button()
        Me.btnOr = New System.Windows.Forms.Button()
        Me.btnReport = New System.Windows.Forms.Button()
        Me.GroupBox5 = New System.Windows.Forms.GroupBox()
        Me.txtNotice = New System.Windows.Forms.TextBox()
        Me.PictureBox1 = New System.Windows.Forms.PictureBox()
        Me.btnSave = New System.Windows.Forms.Button()
        Me.btnDeleteItem = New System.Windows.Forms.Button()
        Me.btnAddItem = New System.Windows.Forms.Button()
        Me.btnOut = New System.Windows.Forms.Button()
        Me.btnNewBill = New System.Windows.Forms.Button()
        Me.btnPrint = New System.Windows.Forms.Button()
        Me.btnSearch = New System.Windows.Forms.Button()
        Me.GroupBox2 = New System.Windows.Forms.GroupBox()
        Me.txtBstyle = New System.Windows.Forms.TextBox()
        Me.lblAddress = New DevExpress.XtraEditors.LabelControl()
        Me.lblTin = New System.Windows.Forms.Label()
        Me.Label17 = New System.Windows.Forms.Label()
        Me.txtperiod = New System.Windows.Forms.TextBox()
        Me.lbldatetrans = New DevExpress.XtraEditors.DateEdit()
        Me.MaskedTextBox1 = New System.Windows.Forms.MaskedTextBox()
        Me.Label14 = New System.Windows.Forms.Label()
        Me.txtnametemp = New System.Windows.Forms.TextBox()
        Me.txtParticulars = New System.Windows.Forms.TextBox()
        Me.Label10 = New System.Windows.Forms.Label()
        Me.Label5 = New System.Windows.Forms.Label()
        Me.labelstatus = New System.Windows.Forms.TextBox()
        Me.txtName = New System.Windows.Forms.TextBox()
        Me.Label3 = New System.Windows.Forms.Label()
        Me.txtSearchIndicator = New System.Windows.Forms.TextBox()
        Me.txtTempno = New System.Windows.Forms.TextBox()
        Me.txtVessel = New System.Windows.Forms.TextBox()
        Me.cbType = New System.Windows.Forms.ComboBox()
        Me.Label2 = New System.Windows.Forms.Label()
        Me.vat = New System.Windows.Forms.CheckBox()
        Me.ppa = New System.Windows.Forms.CheckBox()
        Me.txtVoyage = New System.Windows.Forms.MaskedTextBox()
        Me.txtBillno = New System.Windows.Forms.MaskedTextBox()
        Me.txtAccountno = New System.Windows.Forms.MaskedTextBox()
        Me.Label11 = New System.Windows.Forms.Label()
        Me.cbRoute = New System.Windows.Forms.ComboBox()
        Me.Label8 = New System.Windows.Forms.Label()
        Me.Label7 = New System.Windows.Forms.Label()
        Me.Label6 = New System.Windows.Forms.Label()
        Me.lblAccname = New System.Windows.Forms.Label()
        Me.Label4 = New System.Windows.Forms.Label()
        Me.txtTime = New System.Windows.Forms.Label()
        Me.Label1 = New System.Windows.Forms.Label()
        Me.GroupBox3 = New System.Windows.Forms.GroupBox()
        Me.dgItems = New System.Windows.Forms.DataGridView()
        Me.Column8 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.DataGridViewTextBoxColumn1 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.DataGridViewTextBoxColumn2 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column3 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column5 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column6 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column7 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column9 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column4 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column10 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column11 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column12 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.GroupBox4 = New System.Windows.Forms.GroupBox()
        Me.Label18 = New System.Windows.Forms.Label()
        Me.Label16 = New System.Windows.Forms.Label()
        Me.Label15 = New System.Windows.Forms.Label()
        Me.txtDiscount = New System.Windows.Forms.TextBox()
        Me.txtScipsi = New System.Windows.Forms.TextBox()
        Me.Label9 = New System.Windows.Forms.Label()
        Me.txtVat = New System.Windows.Forms.TextBox()
        Me.txtNet = New System.Windows.Forms.TextBox()
        Me.txtLessppa = New System.Windows.Forms.TextBox()
        Me.txtTotal = New System.Windows.Forms.TextBox()
        Me.Label13 = New System.Windows.Forms.Label()
        Me.Label12 = New System.Windows.Forms.Label()
        Me.txtNetTemp = New System.Windows.Forms.TextBox()
        Me.txtIndicator = New System.Windows.Forms.TextBox()
        Me.MenuStrip1 = New System.Windows.Forms.MenuStrip()
        Me.TransactionToolStripMenuItem1 = New System.Windows.Forms.ToolStripMenuItem()
        Me.BillingStatementToolStripMenuItem1 = New System.Windows.Forms.ToolStripMenuItem()
        Me.TransmittalToolStripMenuItem = New System.Windows.Forms.ToolStripMenuItem()
        Me.YellowBillsToolStripMenuItem = New System.Windows.Forms.ToolStripMenuItem()
        Me.WhiteBillsToolStripMenuItem = New System.Windows.Forms.ToolStripMenuItem()
        Me.PrintToolStripMenuItem = New System.Windows.Forms.ToolStripMenuItem()
        Me.RTCToolStripMenuItem = New System.Windows.Forms.ToolStripMenuItem()
        Me.SearchToolStripMenuItem = New System.Windows.Forms.ToolStripMenuItem()
        Me.CustomerToolStripMenuItem = New System.Windows.Forms.ToolStripMenuItem()
        Me.PaidCustomerToolStripMenuItem = New System.Windows.Forms.ToolStripMenuItem()
        Me.RequestToolStripMenuItem = New System.Windows.Forms.ToolStripMenuItem()
        Me.BillTransactionsToolStripMenuItem = New System.Windows.Forms.ToolStripMenuItem()
        Me.BackDatesToolStripMenuItem = New System.Windows.Forms.ToolStripMenuItem()
        Me.ToolStripMenuItem1 = New System.Windows.Forms.ToolStripMenuItem()
        Me.AboutToolStripMenuItem = New System.Windows.Forms.ToolStripMenuItem()
        Me.UpdateSoftwareToolStripMenuItem = New System.Windows.Forms.ToolStripMenuItem()
        Me.ChangePasswordToolStripMenuItem = New System.Windows.Forms.ToolStripMenuItem()
        Me.SimpleButton1 = New DevExpress.XtraEditors.SimpleButton()
        Me.forsysdate = New System.Windows.Forms.Timer(Me.components)
        Me.fordatereq = New System.Windows.Forms.Timer(Me.components)
        Me.GroupBox1.SuspendLayout()
        Me.GroupBox5.SuspendLayout()
        CType(Me.PictureBox1, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.GroupBox2.SuspendLayout()
        CType(Me.lbldatetrans.Properties.CalendarTimeProperties, System.ComponentModel.ISupportInitialize).BeginInit()
        CType(Me.lbldatetrans.Properties, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.GroupBox3.SuspendLayout()
        CType(Me.dgItems, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.GroupBox4.SuspendLayout()
        Me.MenuStrip1.SuspendLayout()
        Me.SuspendLayout()
        '
        'GroupBox1
        '
        Me.GroupBox1.BackColor = System.Drawing.Color.Transparent
        Me.GroupBox1.Controls.Add(Me.btnUpdate)
        Me.GroupBox1.Controls.Add(Me.btnEdit)
        Me.GroupBox1.Controls.Add(Me.btnOr)
        Me.GroupBox1.Controls.Add(Me.btnReport)
        Me.GroupBox1.Controls.Add(Me.GroupBox5)
        Me.GroupBox1.Controls.Add(Me.btnSave)
        Me.GroupBox1.Controls.Add(Me.btnDeleteItem)
        Me.GroupBox1.Controls.Add(Me.btnAddItem)
        Me.GroupBox1.Controls.Add(Me.btnOut)
        Me.GroupBox1.Controls.Add(Me.btnNewBill)
        Me.GroupBox1.Controls.Add(Me.btnPrint)
        Me.GroupBox1.Controls.Add(Me.btnSearch)
        Me.GroupBox1.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox1.ForeColor = System.Drawing.Color.Black
        Me.GroupBox1.Location = New System.Drawing.Point(12, 35)
        Me.GroupBox1.Name = "GroupBox1"
        Me.GroupBox1.Size = New System.Drawing.Size(1004, 100)
        Me.GroupBox1.TabIndex = 5
        Me.GroupBox1.TabStop = False
        Me.GroupBox1.Text = "FUNCTIONS"
        '
        'btnUpdate
        '
        Me.btnUpdate.BackColor = System.Drawing.Color.Transparent
        Me.btnUpdate.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnUpdate.FlatAppearance.MouseOverBackColor = System.Drawing.Color.FromArgb(CType(CType(0, Byte), Integer), CType(CType(192, Byte), Integer), CType(CType(192, Byte), Integer))
        Me.btnUpdate.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnUpdate.Font = New System.Drawing.Font("Segoe UI", 9.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnUpdate.ForeColor = System.Drawing.Color.Black
        Me.btnUpdate.Image = Global.billing.My.Resources.Resources.save_as_32
        Me.btnUpdate.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnUpdate.Location = New System.Drawing.Point(264, 19)
        Me.btnUpdate.Name = "btnUpdate"
        Me.btnUpdate.Size = New System.Drawing.Size(80, 71)
        Me.btnUpdate.TabIndex = 88
        Me.btnUpdate.TabStop = False
        Me.btnUpdate.Text = "F6 UPDATE"
        Me.btnUpdate.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnUpdate.UseVisualStyleBackColor = False
        Me.btnUpdate.Visible = False
        '
        'btnEdit
        '
        Me.btnEdit.BackColor = System.Drawing.Color.Transparent
        Me.btnEdit.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnEdit.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnEdit.Image = Global.billing.My.Resources.Resources.edit_property_32
        Me.btnEdit.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnEdit.Location = New System.Drawing.Point(264, 19)
        Me.btnEdit.Name = "btnEdit"
        Me.btnEdit.Size = New System.Drawing.Size(80, 71)
        Me.btnEdit.TabIndex = 87
        Me.btnEdit.TabStop = False
        Me.btnEdit.Text = "F5 EDIT"
        Me.btnEdit.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnEdit.UseVisualStyleBackColor = False
        Me.btnEdit.Visible = False
        '
        'btnOr
        '
        Me.btnOr.BackColor = System.Drawing.Color.Transparent
        Me.btnOr.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnOr.FlatAppearance.MouseOverBackColor = System.Drawing.Color.FromArgb(CType(CType(0, Byte), Integer), CType(CType(192, Byte), Integer), CType(CType(192, Byte), Integer))
        Me.btnOr.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnOr.Font = New System.Drawing.Font("Segoe UI", 9.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnOr.ForeColor = System.Drawing.Color.Black
        Me.btnOr.Image = Global.billing.My.Resources.Resources.paper_32
        Me.btnOr.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnOr.Location = New System.Drawing.Point(522, 19)
        Me.btnOr.Name = "btnOr"
        Me.btnOr.Size = New System.Drawing.Size(80, 71)
        Me.btnOr.TabIndex = 33
        Me.btnOr.TabStop = False
        Me.btnOr.Text = "F10 OFF. RECEIPT"
        Me.btnOr.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnOr.UseVisualStyleBackColor = False
        '
        'btnReport
        '
        Me.btnReport.BackColor = System.Drawing.Color.Transparent
        Me.btnReport.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnReport.FlatAppearance.MouseOverBackColor = System.Drawing.Color.FromArgb(CType(CType(0, Byte), Integer), CType(CType(192, Byte), Integer), CType(CType(192, Byte), Integer))
        Me.btnReport.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnReport.Font = New System.Drawing.Font("Segoe UI", 9.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnReport.ForeColor = System.Drawing.Color.Black
        Me.btnReport.Image = Global.billing.My.Resources.Resources.excel_32
        Me.btnReport.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnReport.Location = New System.Drawing.Point(608, 19)
        Me.btnReport.Name = "btnReport"
        Me.btnReport.Size = New System.Drawing.Size(80, 71)
        Me.btnReport.TabIndex = 31
        Me.btnReport.TabStop = False
        Me.btnReport.Text = "F11 REPORTS"
        Me.btnReport.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnReport.UseVisualStyleBackColor = False
        '
        'GroupBox5
        '
        Me.GroupBox5.Controls.Add(Me.txtNotice)
        Me.GroupBox5.Controls.Add(Me.PictureBox1)
        Me.GroupBox5.ForeColor = System.Drawing.Color.Black
        Me.GroupBox5.Location = New System.Drawing.Point(781, 19)
        Me.GroupBox5.Name = "GroupBox5"
        Me.GroupBox5.Size = New System.Drawing.Size(217, 71)
        Me.GroupBox5.TabIndex = 30
        Me.GroupBox5.TabStop = False
        Me.GroupBox5.Text = "NOTICE"
        '
        'txtNotice
        '
        Me.txtNotice.BackColor = System.Drawing.Color.White
        Me.txtNotice.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtNotice.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtNotice.ForeColor = System.Drawing.Color.Maroon
        Me.txtNotice.Location = New System.Drawing.Point(6, 19)
        Me.txtNotice.Multiline = True
        Me.txtNotice.Name = "txtNotice"
        Me.txtNotice.ReadOnly = True
        Me.txtNotice.Size = New System.Drawing.Size(171, 46)
        Me.txtNotice.TabIndex = 32
        Me.txtNotice.TabStop = False
        '
        'PictureBox1
        '
        Me.PictureBox1.Image = Global.billing.My.Resources.Resources.message_26
        Me.PictureBox1.Location = New System.Drawing.Point(183, 19)
        Me.PictureBox1.Name = "PictureBox1"
        Me.PictureBox1.Size = New System.Drawing.Size(28, 25)
        Me.PictureBox1.TabIndex = 31
        Me.PictureBox1.TabStop = False
        '
        'btnSave
        '
        Me.btnSave.BackColor = System.Drawing.Color.Transparent
        Me.btnSave.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnSave.Enabled = False
        Me.btnSave.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnSave.Image = Global.billing.My.Resources.Resources.save_32
        Me.btnSave.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnSave.Location = New System.Drawing.Point(264, 19)
        Me.btnSave.Name = "btnSave"
        Me.btnSave.Size = New System.Drawing.Size(80, 71)
        Me.btnSave.TabIndex = 10
        Me.btnSave.TabStop = False
        Me.btnSave.Text = "F4 SAVE"
        Me.btnSave.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnSave.UseVisualStyleBackColor = False
        '
        'btnDeleteItem
        '
        Me.btnDeleteItem.BackColor = System.Drawing.Color.Transparent
        Me.btnDeleteItem.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnDeleteItem.Enabled = False
        Me.btnDeleteItem.FlatAppearance.MouseOverBackColor = System.Drawing.Color.FromArgb(CType(CType(0, Byte), Integer), CType(CType(192, Byte), Integer), CType(CType(192, Byte), Integer))
        Me.btnDeleteItem.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnDeleteItem.Font = New System.Drawing.Font("Segoe UI", 9.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnDeleteItem.ForeColor = System.Drawing.Color.Black
        Me.btnDeleteItem.Image = Global.billing.My.Resources.Resources.delete_property_32
        Me.btnDeleteItem.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnDeleteItem.Location = New System.Drawing.Point(178, 19)
        Me.btnDeleteItem.Name = "btnDeleteItem"
        Me.btnDeleteItem.Size = New System.Drawing.Size(80, 71)
        Me.btnDeleteItem.TabIndex = 9
        Me.btnDeleteItem.TabStop = False
        Me.btnDeleteItem.Text = "F3 DELETE ITEM"
        Me.btnDeleteItem.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnDeleteItem.UseVisualStyleBackColor = False
        '
        'btnAddItem
        '
        Me.btnAddItem.BackColor = System.Drawing.Color.Transparent
        Me.btnAddItem.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnAddItem.Enabled = False
        Me.btnAddItem.FlatAppearance.MouseOverBackColor = System.Drawing.Color.FromArgb(CType(CType(0, Byte), Integer), CType(CType(192, Byte), Integer), CType(CType(192, Byte), Integer))
        Me.btnAddItem.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnAddItem.Font = New System.Drawing.Font("Segoe UI", 9.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnAddItem.ForeColor = System.Drawing.Color.Black
        Me.btnAddItem.Image = Global.billing.My.Resources.Resources.add_list_32
        Me.btnAddItem.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnAddItem.Location = New System.Drawing.Point(92, 19)
        Me.btnAddItem.Name = "btnAddItem"
        Me.btnAddItem.Size = New System.Drawing.Size(80, 71)
        Me.btnAddItem.TabIndex = 8
        Me.btnAddItem.TabStop = False
        Me.btnAddItem.Text = "F2 ADD ITEM"
        Me.btnAddItem.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnAddItem.UseVisualStyleBackColor = False
        '
        'btnOut
        '
        Me.btnOut.BackColor = System.Drawing.Color.Transparent
        Me.btnOut.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnOut.FlatAppearance.MouseOverBackColor = System.Drawing.Color.FromArgb(CType(CType(0, Byte), Integer), CType(CType(192, Byte), Integer), CType(CType(192, Byte), Integer))
        Me.btnOut.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnOut.Font = New System.Drawing.Font("Segoe UI", 9.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnOut.ForeColor = System.Drawing.Color.Black
        Me.btnOut.Image = Global.billing.My.Resources.Resources.shutdown_32
        Me.btnOut.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnOut.Location = New System.Drawing.Point(694, 19)
        Me.btnOut.Name = "btnOut"
        Me.btnOut.Size = New System.Drawing.Size(80, 71)
        Me.btnOut.TabIndex = 7
        Me.btnOut.TabStop = False
        Me.btnOut.Text = "F12 LOG OUT"
        Me.btnOut.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnOut.UseVisualStyleBackColor = False
        '
        'btnNewBill
        '
        Me.btnNewBill.BackColor = System.Drawing.Color.Transparent
        Me.btnNewBill.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnNewBill.FlatAppearance.MouseOverBackColor = System.Drawing.Color.FromArgb(CType(CType(0, Byte), Integer), CType(CType(192, Byte), Integer), CType(CType(192, Byte), Integer))
        Me.btnNewBill.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnNewBill.Font = New System.Drawing.Font("Segoe UI", 9.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnNewBill.ForeColor = System.Drawing.Color.Black
        Me.btnNewBill.Image = Global.billing.My.Resources.Resources.google_news_32
        Me.btnNewBill.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnNewBill.Location = New System.Drawing.Point(6, 19)
        Me.btnNewBill.Name = "btnNewBill"
        Me.btnNewBill.Size = New System.Drawing.Size(80, 71)
        Me.btnNewBill.TabIndex = 6
        Me.btnNewBill.TabStop = False
        Me.btnNewBill.Text = "F1 NEW BILL"
        Me.btnNewBill.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnNewBill.UseVisualStyleBackColor = False
        '
        'btnPrint
        '
        Me.btnPrint.BackColor = System.Drawing.Color.Transparent
        Me.btnPrint.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnPrint.Enabled = False
        Me.btnPrint.FlatAppearance.MouseOverBackColor = System.Drawing.Color.FromArgb(CType(CType(0, Byte), Integer), CType(CType(192, Byte), Integer), CType(CType(192, Byte), Integer))
        Me.btnPrint.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnPrint.Font = New System.Drawing.Font("Segoe UI", 9.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnPrint.ForeColor = System.Drawing.Color.Black
        Me.btnPrint.Image = Global.billing.My.Resources.Resources.print_32
        Me.btnPrint.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnPrint.Location = New System.Drawing.Point(350, 19)
        Me.btnPrint.Name = "btnPrint"
        Me.btnPrint.Size = New System.Drawing.Size(80, 71)
        Me.btnPrint.TabIndex = 5
        Me.btnPrint.TabStop = False
        Me.btnPrint.Text = "F8 RE-PRINT"
        Me.btnPrint.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnPrint.UseVisualStyleBackColor = False
        '
        'btnSearch
        '
        Me.btnSearch.BackColor = System.Drawing.Color.Transparent
        Me.btnSearch.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnSearch.FlatAppearance.MouseOverBackColor = System.Drawing.Color.FromArgb(CType(CType(0, Byte), Integer), CType(CType(192, Byte), Integer), CType(CType(192, Byte), Integer))
        Me.btnSearch.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnSearch.Font = New System.Drawing.Font("Segoe UI", 9.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnSearch.ForeColor = System.Drawing.Color.Black
        Me.btnSearch.Image = Global.billing.My.Resources.Resources.search_32
        Me.btnSearch.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnSearch.Location = New System.Drawing.Point(436, 19)
        Me.btnSearch.Name = "btnSearch"
        Me.btnSearch.Size = New System.Drawing.Size(80, 71)
        Me.btnSearch.TabIndex = 4
        Me.btnSearch.TabStop = False
        Me.btnSearch.Text = "F9 SEARCH"
        Me.btnSearch.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnSearch.UseVisualStyleBackColor = False
        '
        'GroupBox2
        '
        Me.GroupBox2.BackColor = System.Drawing.Color.Transparent
        Me.GroupBox2.Controls.Add(Me.txtBstyle)
        Me.GroupBox2.Controls.Add(Me.lblAddress)
        Me.GroupBox2.Controls.Add(Me.lblTin)
        Me.GroupBox2.Controls.Add(Me.Label17)
        Me.GroupBox2.Controls.Add(Me.txtperiod)
        Me.GroupBox2.Controls.Add(Me.lbldatetrans)
        Me.GroupBox2.Controls.Add(Me.MaskedTextBox1)
        Me.GroupBox2.Controls.Add(Me.Label14)
        Me.GroupBox2.Controls.Add(Me.txtnametemp)
        Me.GroupBox2.Controls.Add(Me.txtParticulars)
        Me.GroupBox2.Controls.Add(Me.Label10)
        Me.GroupBox2.Controls.Add(Me.Label5)
        Me.GroupBox2.Controls.Add(Me.labelstatus)
        Me.GroupBox2.Controls.Add(Me.txtName)
        Me.GroupBox2.Controls.Add(Me.Label3)
        Me.GroupBox2.Controls.Add(Me.txtSearchIndicator)
        Me.GroupBox2.Controls.Add(Me.txtTempno)
        Me.GroupBox2.Controls.Add(Me.txtVessel)
        Me.GroupBox2.Controls.Add(Me.cbType)
        Me.GroupBox2.Controls.Add(Me.Label2)
        Me.GroupBox2.Controls.Add(Me.vat)
        Me.GroupBox2.Controls.Add(Me.ppa)
        Me.GroupBox2.Controls.Add(Me.txtVoyage)
        Me.GroupBox2.Controls.Add(Me.txtBillno)
        Me.GroupBox2.Controls.Add(Me.txtAccountno)
        Me.GroupBox2.Controls.Add(Me.Label11)
        Me.GroupBox2.Controls.Add(Me.cbRoute)
        Me.GroupBox2.Controls.Add(Me.Label8)
        Me.GroupBox2.Controls.Add(Me.Label7)
        Me.GroupBox2.Controls.Add(Me.Label6)
        Me.GroupBox2.Controls.Add(Me.lblAccname)
        Me.GroupBox2.Controls.Add(Me.Label4)
        Me.GroupBox2.Controls.Add(Me.txtTime)
        Me.GroupBox2.Controls.Add(Me.Label1)
        Me.GroupBox2.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox2.ForeColor = System.Drawing.Color.Black
        Me.GroupBox2.Location = New System.Drawing.Point(12, 142)
        Me.GroupBox2.Name = "GroupBox2"
        Me.GroupBox2.Size = New System.Drawing.Size(1004, 152)
        Me.GroupBox2.TabIndex = 24
        Me.GroupBox2.TabStop = False
        Me.GroupBox2.Text = "BILLING INFO"
        '
        'txtBstyle
        '
        Me.txtBstyle.Location = New System.Drawing.Point(593, 84)
        Me.txtBstyle.Name = "txtBstyle"
        Me.txtBstyle.Size = New System.Drawing.Size(51, 22)
        Me.txtBstyle.TabIndex = 117
        Me.txtBstyle.Visible = False
        '
        'lblAddress
        '
        Me.lblAddress.Appearance.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold)
        Me.lblAddress.AutoSizeMode = DevExpress.XtraEditors.LabelAutoSizeMode.Vertical
        Me.lblAddress.Location = New System.Drawing.Point(235, 66)
        Me.lblAddress.Margin = New System.Windows.Forms.Padding(2)
        Me.lblAddress.Name = "lblAddress"
        Me.lblAddress.Size = New System.Drawing.Size(537, 0)
        Me.lblAddress.TabIndex = 116
        '
        'lblTin
        '
        Me.lblTin.AutoSize = True
        Me.lblTin.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblTin.ForeColor = System.Drawing.Color.Black
        Me.lblTin.Location = New System.Drawing.Point(587, 43)
        Me.lblTin.Name = "lblTin"
        Me.lblTin.Size = New System.Drawing.Size(0, 17)
        Me.lblTin.TabIndex = 115
        '
        'Label17
        '
        Me.Label17.AutoSize = True
        Me.Label17.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label17.ForeColor = System.Drawing.Color.Black
        Me.Label17.Location = New System.Drawing.Point(232, 66)
        Me.Label17.Name = "Label17"
        Me.Label17.Size = New System.Drawing.Size(0, 17)
        Me.Label17.TabIndex = 114
        '
        'txtperiod
        '
        Me.txtperiod.Location = New System.Drawing.Point(452, 65)
        Me.txtperiod.Name = "txtperiod"
        Me.txtperiod.Size = New System.Drawing.Size(100, 22)
        Me.txtperiod.TabIndex = 104
        Me.txtperiod.Visible = False
        '
        'lbldatetrans
        '
        Me.lbldatetrans.EditValue = Nothing
        Me.lbldatetrans.Enabled = False
        Me.lbldatetrans.Location = New System.Drawing.Point(850, 36)
        Me.lbldatetrans.Name = "lbldatetrans"
        Me.lbldatetrans.Properties.Appearance.BackColor = System.Drawing.Color.Transparent
        Me.lbldatetrans.Properties.Appearance.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lbldatetrans.Properties.Appearance.ForeColor = System.Drawing.Color.Maroon
        Me.lbldatetrans.Properties.Appearance.Options.UseBackColor = True
        Me.lbldatetrans.Properties.Appearance.Options.UseFont = True
        Me.lbldatetrans.Properties.Appearance.Options.UseForeColor = True
        Me.lbldatetrans.Properties.BorderStyle = DevExpress.XtraEditors.Controls.BorderStyles.UltraFlat
        Me.lbldatetrans.Properties.Buttons.AddRange(New DevExpress.XtraEditors.Controls.EditorButton() {New DevExpress.XtraEditors.Controls.EditorButton(DevExpress.XtraEditors.Controls.ButtonPredefines.Combo)})
        Me.lbldatetrans.Properties.CalendarTimeProperties.Buttons.AddRange(New DevExpress.XtraEditors.Controls.EditorButton() {New DevExpress.XtraEditors.Controls.EditorButton(DevExpress.XtraEditors.Controls.ButtonPredefines.Combo)})
        Me.lbldatetrans.RightToLeft = System.Windows.Forms.RightToLeft.Yes
        Me.lbldatetrans.Size = New System.Drawing.Size(139, 24)
        Me.lbldatetrans.TabIndex = 103
        '
        'MaskedTextBox1
        '
        Me.MaskedTextBox1.BackColor = System.Drawing.Color.White
        Me.MaskedTextBox1.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.MaskedTextBox1.Enabled = False
        Me.MaskedTextBox1.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.MaskedTextBox1.ForeColor = System.Drawing.Color.Black
        Me.MaskedTextBox1.Location = New System.Drawing.Point(105, 18)
        Me.MaskedTextBox1.Mask = "##########"
        Me.MaskedTextBox1.Name = "MaskedTextBox1"
        Me.MaskedTextBox1.Size = New System.Drawing.Size(91, 18)
        Me.MaskedTextBox1.TabIndex = 101
        Me.MaskedTextBox1.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'Label14
        '
        Me.Label14.AutoSize = True
        Me.Label14.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label14.ForeColor = System.Drawing.Color.Black
        Me.Label14.Location = New System.Drawing.Point(17, 18)
        Me.Label14.Name = "Label14"
        Me.Label14.Size = New System.Drawing.Size(82, 17)
        Me.Label14.TabIndex = 102
        Me.Label14.Text = "CONTROL #:"
        '
        'txtnametemp
        '
        Me.txtnametemp.Location = New System.Drawing.Point(339, -6)
        Me.txtnametemp.Name = "txtnametemp"
        Me.txtnametemp.Size = New System.Drawing.Size(100, 22)
        Me.txtnametemp.TabIndex = 99
        Me.txtnametemp.Visible = False
        '
        'txtParticulars
        '
        Me.txtParticulars.BackColor = System.Drawing.Color.White
        Me.txtParticulars.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtParticulars.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtParticulars.ForeColor = System.Drawing.Color.Black
        Me.txtParticulars.Location = New System.Drawing.Point(105, 131)
        Me.txtParticulars.MaxLength = 150
        Me.txtParticulars.Name = "txtParticulars"
        Me.txtParticulars.Size = New System.Drawing.Size(887, 18)
        Me.txtParticulars.TabIndex = 95
        '
        'Label10
        '
        Me.Label10.AutoSize = True
        Me.Label10.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label10.ForeColor = System.Drawing.Color.Black
        Me.Label10.Location = New System.Drawing.Point(741, 37)
        Me.Label10.Name = "Label10"
        Me.Label10.Size = New System.Drawing.Size(105, 17)
        Me.Label10.TabIndex = 94
        Me.Label10.Text = "DATE OF TRANS:"
        '
        'Label5
        '
        Me.Label5.AutoSize = True
        Me.Label5.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label5.ForeColor = System.Drawing.Color.Black
        Me.Label5.Location = New System.Drawing.Point(741, 16)
        Me.Label5.Name = "Label5"
        Me.Label5.Size = New System.Drawing.Size(92, 17)
        Me.Label5.TabIndex = 92
        Me.Label5.Text = "SYSTEM DATE:"
        '
        'labelstatus
        '
        Me.labelstatus.Location = New System.Drawing.Point(699, 0)
        Me.labelstatus.Name = "labelstatus"
        Me.labelstatus.Size = New System.Drawing.Size(100, 22)
        Me.labelstatus.TabIndex = 91
        Me.labelstatus.Text = "0"
        Me.labelstatus.Visible = False
        '
        'txtName
        '
        Me.txtName.BackColor = System.Drawing.Color.SkyBlue
        Me.txtName.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtName.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtName.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtName.ForeColor = System.Drawing.Color.Black
        Me.txtName.Location = New System.Drawing.Point(316, 14)
        Me.txtName.Name = "txtName"
        Me.txtName.ReadOnly = True
        Me.txtName.Size = New System.Drawing.Size(515, 18)
        Me.txtName.TabIndex = 90
        Me.txtName.TabStop = False
        '
        'Label3
        '
        Me.Label3.AutoSize = True
        Me.Label3.Font = New System.Drawing.Font("Microsoft Sans Serif", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label3.ForeColor = System.Drawing.Color.Black
        Me.Label3.Location = New System.Drawing.Point(207, 16)
        Me.Label3.Name = "Label3"
        Me.Label3.Size = New System.Drawing.Size(103, 16)
        Me.Label3.TabIndex = 89
        Me.Label3.Text = "BILLING DEPT.:"
        '
        'txtSearchIndicator
        '
        Me.txtSearchIndicator.Location = New System.Drawing.Point(593, 0)
        Me.txtSearchIndicator.Name = "txtSearchIndicator"
        Me.txtSearchIndicator.Size = New System.Drawing.Size(100, 22)
        Me.txtSearchIndicator.TabIndex = 88
        Me.txtSearchIndicator.Text = "0"
        Me.txtSearchIndicator.Visible = False
        '
        'txtTempno
        '
        Me.txtTempno.Location = New System.Drawing.Point(487, 0)
        Me.txtTempno.Name = "txtTempno"
        Me.txtTempno.Size = New System.Drawing.Size(100, 22)
        Me.txtTempno.TabIndex = 87
        Me.txtTempno.Visible = False
        '
        'txtVessel
        '
        Me.txtVessel.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtVessel.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtVessel.BackColor = System.Drawing.Color.White
        Me.txtVessel.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtVessel.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtVessel.Enabled = False
        Me.txtVessel.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtVessel.ForeColor = System.Drawing.Color.Black
        Me.txtVessel.Location = New System.Drawing.Point(105, 70)
        Me.txtVessel.MaxLength = 30
        Me.txtVessel.Name = "txtVessel"
        Me.txtVessel.Size = New System.Drawing.Size(308, 18)
        Me.txtVessel.TabIndex = 3
        '
        'cbType
        '
        Me.cbType.BackColor = System.Drawing.Color.White
        Me.cbType.DropDownStyle = System.Windows.Forms.ComboBoxStyle.DropDownList
        Me.cbType.Enabled = False
        Me.cbType.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.cbType.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.cbType.ForeColor = System.Drawing.Color.Black
        Me.cbType.Items.AddRange(New Object() {"IN", "OUT"})
        Me.cbType.Location = New System.Drawing.Point(260, 108)
        Me.cbType.Name = "cbType"
        Me.cbType.Size = New System.Drawing.Size(49, 25)
        Me.cbType.TabIndex = 6
        '
        'Label2
        '
        Me.Label2.AutoSize = True
        Me.Label2.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label2.Location = New System.Drawing.Point(207, 110)
        Me.Label2.Name = "Label2"
        Me.Label2.Size = New System.Drawing.Size(39, 17)
        Me.Label2.TabIndex = 45
        Me.Label2.Text = "TYPE:"
        '
        'vat
        '
        Me.vat.AutoSize = True
        Me.vat.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.vat.Checked = True
        Me.vat.CheckState = System.Windows.Forms.CheckState.Checked
        Me.vat.Enabled = False
        Me.vat.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.vat.ForeColor = System.Drawing.Color.Black
        Me.vat.Location = New System.Drawing.Point(388, 108)
        Me.vat.Name = "vat"
        Me.vat.Size = New System.Drawing.Size(48, 21)
        Me.vat.TabIndex = 9
        Me.vat.Text = "VAT"
        Me.vat.UseVisualStyleBackColor = True
        '
        'ppa
        '
        Me.ppa.AutoSize = True
        Me.ppa.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.ppa.Checked = True
        Me.ppa.CheckState = System.Windows.Forms.CheckState.Checked
        Me.ppa.Enabled = False
        Me.ppa.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.ppa.ForeColor = System.Drawing.Color.Black
        Me.ppa.Location = New System.Drawing.Point(331, 108)
        Me.ppa.Name = "ppa"
        Me.ppa.Size = New System.Drawing.Size(48, 21)
        Me.ppa.TabIndex = 8
        Me.ppa.Text = "PPA"
        Me.ppa.UseVisualStyleBackColor = True
        '
        'txtVoyage
        '
        Me.txtVoyage.BackColor = System.Drawing.Color.White
        Me.txtVoyage.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtVoyage.Enabled = False
        Me.txtVoyage.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtVoyage.ForeColor = System.Drawing.Color.Black
        Me.txtVoyage.Location = New System.Drawing.Point(105, 88)
        Me.txtVoyage.Mask = "##########"
        Me.txtVoyage.Name = "txtVoyage"
        Me.txtVoyage.Size = New System.Drawing.Size(91, 18)
        Me.txtVoyage.TabIndex = 4
        Me.txtVoyage.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'txtBillno
        '
        Me.txtBillno.BackColor = System.Drawing.Color.White
        Me.txtBillno.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtBillno.Enabled = False
        Me.txtBillno.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtBillno.ForeColor = System.Drawing.Color.Black
        Me.txtBillno.Location = New System.Drawing.Point(105, 35)
        Me.txtBillno.Mask = "##########"
        Me.txtBillno.Name = "txtBillno"
        Me.txtBillno.Size = New System.Drawing.Size(91, 18)
        Me.txtBillno.TabIndex = 1
        Me.txtBillno.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'txtAccountno
        '
        Me.txtAccountno.BackColor = System.Drawing.Color.White
        Me.txtAccountno.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtAccountno.Enabled = False
        Me.txtAccountno.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtAccountno.ForeColor = System.Drawing.Color.Black
        Me.txtAccountno.Location = New System.Drawing.Point(105, 52)
        Me.txtAccountno.Mask = "#####-####"
        Me.txtAccountno.Name = "txtAccountno"
        Me.txtAccountno.Size = New System.Drawing.Size(91, 18)
        Me.txtAccountno.TabIndex = 2
        Me.txtAccountno.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'Label11
        '
        Me.Label11.AutoSize = True
        Me.Label11.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label11.ForeColor = System.Drawing.Color.Black
        Me.Label11.Location = New System.Drawing.Point(17, 131)
        Me.Label11.Name = "Label11"
        Me.Label11.Size = New System.Drawing.Size(87, 17)
        Me.Label11.TabIndex = 41
        Me.Label11.Text = "PARTICULARs:"
        '
        'cbRoute
        '
        Me.cbRoute.BackColor = System.Drawing.Color.White
        Me.cbRoute.DropDownStyle = System.Windows.Forms.ComboBoxStyle.DropDownList
        Me.cbRoute.Enabled = False
        Me.cbRoute.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.cbRoute.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.cbRoute.ForeColor = System.Drawing.Color.Black
        Me.cbRoute.Items.AddRange(New Object() {"DOMESTIC", "FOREIGN"})
        Me.cbRoute.Location = New System.Drawing.Point(105, 106)
        Me.cbRoute.Name = "cbRoute"
        Me.cbRoute.Size = New System.Drawing.Size(91, 25)
        Me.cbRoute.TabIndex = 5
        '
        'Label8
        '
        Me.Label8.AutoSize = True
        Me.Label8.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label8.ForeColor = System.Drawing.Color.Black
        Me.Label8.Location = New System.Drawing.Point(17, 110)
        Me.Label8.Name = "Label8"
        Me.Label8.Size = New System.Drawing.Size(52, 17)
        Me.Label8.TabIndex = 35
        Me.Label8.Text = "ROUTE:"
        '
        'Label7
        '
        Me.Label7.AutoSize = True
        Me.Label7.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label7.ForeColor = System.Drawing.Color.Black
        Me.Label7.Location = New System.Drawing.Point(17, 91)
        Me.Label7.Name = "Label7"
        Me.Label7.Size = New System.Drawing.Size(71, 17)
        Me.Label7.TabIndex = 33
        Me.Label7.Text = "VOYAGE #:"
        '
        'Label6
        '
        Me.Label6.AutoSize = True
        Me.Label6.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label6.ForeColor = System.Drawing.Color.Black
        Me.Label6.Location = New System.Drawing.Point(17, 73)
        Me.Label6.Name = "Label6"
        Me.Label6.Size = New System.Drawing.Size(53, 17)
        Me.Label6.TabIndex = 31
        Me.Label6.Text = "VESSEL:"
        '
        'lblAccname
        '
        Me.lblAccname.AutoSize = True
        Me.lblAccname.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblAccname.ForeColor = System.Drawing.Color.Black
        Me.lblAccname.Location = New System.Drawing.Point(231, 52)
        Me.lblAccname.Name = "lblAccname"
        Me.lblAccname.Size = New System.Drawing.Size(0, 17)
        Me.lblAccname.TabIndex = 30
        '
        'Label4
        '
        Me.Label4.AutoSize = True
        Me.Label4.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label4.ForeColor = System.Drawing.Color.Black
        Me.Label4.Location = New System.Drawing.Point(17, 56)
        Me.Label4.Name = "Label4"
        Me.Label4.Size = New System.Drawing.Size(83, 17)
        Me.Label4.TabIndex = 28
        Me.Label4.Text = "ACCOUNT #:"
        '
        'txtTime
        '
        Me.txtTime.AutoSize = True
        Me.txtTime.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtTime.ForeColor = System.Drawing.Color.Black
        Me.txtTime.Location = New System.Drawing.Point(848, 16)
        Me.txtTime.Name = "txtTime"
        Me.txtTime.Size = New System.Drawing.Size(74, 17)
        Me.txtTime.TabIndex = 27
        Me.txtTime.Text = "11/11/1111"
        '
        'Label1
        '
        Me.Label1.AutoSize = True
        Me.Label1.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label1.ForeColor = System.Drawing.Color.Black
        Me.Label1.Location = New System.Drawing.Point(17, 38)
        Me.Label1.Name = "Label1"
        Me.Label1.Size = New System.Drawing.Size(45, 17)
        Me.Label1.TabIndex = 25
        Me.Label1.Text = "BILL #:"
        '
        'GroupBox3
        '
        Me.GroupBox3.BackColor = System.Drawing.Color.Transparent
        Me.GroupBox3.Controls.Add(Me.dgItems)
        Me.GroupBox3.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox3.ForeColor = System.Drawing.Color.Black
        Me.GroupBox3.Location = New System.Drawing.Point(12, 300)
        Me.GroupBox3.Name = "GroupBox3"
        Me.GroupBox3.Size = New System.Drawing.Size(1004, 208)
        Me.GroupBox3.TabIndex = 25
        Me.GroupBox3.TabStop = False
        Me.GroupBox3.Text = "BILLING ITEM"
        '
        'dgItems
        '
        Me.dgItems.AllowUserToAddRows = False
        Me.dgItems.AllowUserToDeleteRows = False
        Me.dgItems.AllowUserToResizeColumns = False
        Me.dgItems.AllowUserToResizeRows = False
        DataGridViewCellStyle1.BackColor = System.Drawing.Color.Aqua
        DataGridViewCellStyle1.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.dgItems.AlternatingRowsDefaultCellStyle = DataGridViewCellStyle1
        Me.dgItems.AutoSizeRowsMode = System.Windows.Forms.DataGridViewAutoSizeRowsMode.AllCellsExceptHeaders
        Me.dgItems.BackgroundColor = System.Drawing.Color.White
        Me.dgItems.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.dgItems.CellBorderStyle = System.Windows.Forms.DataGridViewCellBorderStyle.SunkenHorizontal
        DataGridViewCellStyle2.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleCenter
        DataGridViewCellStyle2.BackColor = System.Drawing.Color.Black
        DataGridViewCellStyle2.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle2.ForeColor = System.Drawing.Color.Yellow
        DataGridViewCellStyle2.SelectionBackColor = System.Drawing.SystemColors.Highlight
        DataGridViewCellStyle2.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        DataGridViewCellStyle2.WrapMode = System.Windows.Forms.DataGridViewTriState.[False]
        Me.dgItems.ColumnHeadersDefaultCellStyle = DataGridViewCellStyle2
        Me.dgItems.ColumnHeadersHeightSizeMode = System.Windows.Forms.DataGridViewColumnHeadersHeightSizeMode.AutoSize
        Me.dgItems.Columns.AddRange(New System.Windows.Forms.DataGridViewColumn() {Me.Column8, Me.DataGridViewTextBoxColumn1, Me.DataGridViewTextBoxColumn2, Me.Column3, Me.Column5, Me.Column6, Me.Column7, Me.Column9, Me.Column4, Me.Column10, Me.Column11, Me.Column12})
        DataGridViewCellStyle3.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleLeft
        DataGridViewCellStyle3.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle3.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle3.ForeColor = System.Drawing.Color.Black
        DataGridViewCellStyle3.SelectionBackColor = System.Drawing.SystemColors.Highlight
        DataGridViewCellStyle3.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        DataGridViewCellStyle3.WrapMode = System.Windows.Forms.DataGridViewTriState.[False]
        Me.dgItems.DefaultCellStyle = DataGridViewCellStyle3
        Me.dgItems.EditMode = System.Windows.Forms.DataGridViewEditMode.EditProgrammatically
        Me.dgItems.ImeMode = System.Windows.Forms.ImeMode.Disable
        Me.dgItems.Location = New System.Drawing.Point(12, 19)
        Me.dgItems.MultiSelect = False
        Me.dgItems.Name = "dgItems"
        Me.dgItems.ReadOnly = True
        Me.dgItems.RowHeadersBorderStyle = System.Windows.Forms.DataGridViewHeaderBorderStyle.Sunken
        DataGridViewCellStyle4.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleLeft
        DataGridViewCellStyle4.BackColor = System.Drawing.Color.Black
        DataGridViewCellStyle4.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle4.ForeColor = System.Drawing.Color.Yellow
        DataGridViewCellStyle4.SelectionBackColor = System.Drawing.SystemColors.Highlight
        DataGridViewCellStyle4.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        Me.dgItems.RowHeadersDefaultCellStyle = DataGridViewCellStyle4
        Me.dgItems.RowHeadersVisible = False
        Me.dgItems.RowHeadersWidthSizeMode = System.Windows.Forms.DataGridViewRowHeadersWidthSizeMode.DisableResizing
        DataGridViewCellStyle5.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleCenter
        DataGridViewCellStyle5.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle5.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle5.ForeColor = System.Drawing.Color.Black
        Me.dgItems.RowsDefaultCellStyle = DataGridViewCellStyle5
        Me.dgItems.ScrollBars = System.Windows.Forms.ScrollBars.Vertical
        Me.dgItems.SelectionMode = System.Windows.Forms.DataGridViewSelectionMode.FullRowSelect
        Me.dgItems.ShowCellErrors = False
        Me.dgItems.ShowCellToolTips = False
        Me.dgItems.ShowEditingIcon = False
        Me.dgItems.ShowRowErrors = False
        Me.dgItems.Size = New System.Drawing.Size(980, 176)
        Me.dgItems.TabIndex = 102
        Me.dgItems.TabStop = False
        '
        'Column8
        '
        Me.Column8.DataPropertyName = "it_id"
        Me.Column8.HeaderText = "id"
        Me.Column8.Name = "Column8"
        Me.Column8.ReadOnly = True
        Me.Column8.Visible = False
        Me.Column8.Width = 50
        '
        'DataGridViewTextBoxColumn1
        '
        Me.DataGridViewTextBoxColumn1.DataPropertyName = "it_qty"
        Me.DataGridViewTextBoxColumn1.HeaderText = "QTY"
        Me.DataGridViewTextBoxColumn1.Name = "DataGridViewTextBoxColumn1"
        Me.DataGridViewTextBoxColumn1.ReadOnly = True
        Me.DataGridViewTextBoxColumn1.Width = 70
        '
        'DataGridViewTextBoxColumn2
        '
        Me.DataGridViewTextBoxColumn2.DataPropertyName = "it_unit"
        Me.DataGridViewTextBoxColumn2.HeaderText = "UNIT"
        Me.DataGridViewTextBoxColumn2.Name = "DataGridViewTextBoxColumn2"
        Me.DataGridViewTextBoxColumn2.ReadOnly = True
        '
        'Column3
        '
        Me.Column3.AutoSizeMode = System.Windows.Forms.DataGridViewAutoSizeColumnMode.Fill
        Me.Column3.DataPropertyName = "it_service"
        Me.Column3.HeaderText = "SERVICE"
        Me.Column3.Name = "Column3"
        Me.Column3.ReadOnly = True
        '
        'Column5
        '
        Me.Column5.AutoSizeMode = System.Windows.Forms.DataGridViewAutoSizeColumnMode.Fill
        Me.Column5.DataPropertyName = "it_cargo"
        Me.Column5.HeaderText = "CARGO"
        Me.Column5.Name = "Column5"
        Me.Column5.ReadOnly = True
        '
        'Column6
        '
        Me.Column6.DataPropertyName = "it_rate"
        Me.Column6.HeaderText = "RATE"
        Me.Column6.Name = "Column6"
        Me.Column6.ReadOnly = True
        '
        'Column7
        '
        Me.Column7.DataPropertyName = "it_disc"
        Me.Column7.HeaderText = "DISC."
        Me.Column7.Name = "Column7"
        Me.Column7.ReadOnly = True
        '
        'Column9
        '
        Me.Column9.DataPropertyName = "it_gross"
        Me.Column9.HeaderText = "GROSS"
        Me.Column9.Name = "Column9"
        Me.Column9.ReadOnly = True
        '
        'Column4
        '
        Me.Column4.DataPropertyName = "it_ppa"
        Me.Column4.HeaderText = "ppa"
        Me.Column4.Name = "Column4"
        Me.Column4.ReadOnly = True
        Me.Column4.Visible = False
        '
        'Column10
        '
        Me.Column10.DataPropertyName = "it_net"
        Me.Column10.HeaderText = "net"
        Me.Column10.Name = "Column10"
        Me.Column10.ReadOnly = True
        Me.Column10.Visible = False
        '
        'Column11
        '
        Me.Column11.DataPropertyName = "it_tax"
        Me.Column11.HeaderText = "vat"
        Me.Column11.Name = "Column11"
        Me.Column11.ReadOnly = True
        Me.Column11.Visible = False
        '
        'Column12
        '
        Me.Column12.DataPropertyName = "it_scipsi"
        Me.Column12.HeaderText = "scipsi"
        Me.Column12.Name = "Column12"
        Me.Column12.ReadOnly = True
        Me.Column12.Visible = False
        '
        'GroupBox4
        '
        Me.GroupBox4.BackColor = System.Drawing.Color.Transparent
        Me.GroupBox4.Controls.Add(Me.Label18)
        Me.GroupBox4.Controls.Add(Me.Label16)
        Me.GroupBox4.Controls.Add(Me.Label15)
        Me.GroupBox4.Controls.Add(Me.txtDiscount)
        Me.GroupBox4.Controls.Add(Me.txtScipsi)
        Me.GroupBox4.Controls.Add(Me.Label9)
        Me.GroupBox4.Controls.Add(Me.txtVat)
        Me.GroupBox4.Controls.Add(Me.txtNet)
        Me.GroupBox4.Controls.Add(Me.txtLessppa)
        Me.GroupBox4.Controls.Add(Me.txtTotal)
        Me.GroupBox4.Controls.Add(Me.Label13)
        Me.GroupBox4.Controls.Add(Me.Label12)
        Me.GroupBox4.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox4.ForeColor = System.Drawing.Color.Black
        Me.GroupBox4.Location = New System.Drawing.Point(12, 514)
        Me.GroupBox4.Name = "GroupBox4"
        Me.GroupBox4.Size = New System.Drawing.Size(1004, 81)
        Me.GroupBox4.TabIndex = 86
        Me.GroupBox4.TabStop = False
        Me.GroupBox4.Text = "COMPUTATIONS"
        '
        'Label18
        '
        Me.Label18.AutoSize = True
        Me.Label18.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label18.ForeColor = System.Drawing.Color.Black
        Me.Label18.Location = New System.Drawing.Point(29, 51)
        Me.Label18.Name = "Label18"
        Me.Label18.Size = New System.Drawing.Size(113, 17)
        Me.Label18.TabIndex = 100
        Me.Label18.Text = "LESS PPA SHARE:"
        '
        'Label16
        '
        Me.Label16.AutoSize = True
        Me.Label16.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label16.ForeColor = System.Drawing.Color.Black
        Me.Label16.Location = New System.Drawing.Point(660, 53)
        Me.Label16.Name = "Label16"
        Me.Label16.Size = New System.Drawing.Size(102, 17)
        Me.Label16.TabIndex = 90
        Me.Label16.Text = "DUE TO SCIPSI:"
        '
        'Label15
        '
        Me.Label15.AutoSize = True
        Me.Label15.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label15.ForeColor = System.Drawing.Color.Black
        Me.Label15.Location = New System.Drawing.Point(693, 24)
        Me.Label15.Name = "Label15"
        Me.Label15.Size = New System.Drawing.Size(65, 17)
        Me.Label15.TabIndex = 89
        Me.Label15.Text = "12% VAT:"
        '
        'txtDiscount
        '
        Me.txtDiscount.BackColor = System.Drawing.Color.SkyBlue
        Me.txtDiscount.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtDiscount.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtDiscount.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtDiscount.ForeColor = System.Drawing.Color.Purple
        Me.txtDiscount.Location = New System.Drawing.Point(430, 22)
        Me.txtDiscount.MaxLength = 30
        Me.txtDiscount.Name = "txtDiscount"
        Me.txtDiscount.ReadOnly = True
        Me.txtDiscount.Size = New System.Drawing.Size(206, 18)
        Me.txtDiscount.TabIndex = 99
        Me.txtDiscount.TabStop = False
        Me.txtDiscount.Text = "0000000000000000000"
        Me.txtDiscount.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'txtScipsi
        '
        Me.txtScipsi.BackColor = System.Drawing.Color.SkyBlue
        Me.txtScipsi.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtScipsi.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtScipsi.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtScipsi.ForeColor = System.Drawing.Color.Red
        Me.txtScipsi.Location = New System.Drawing.Point(769, 53)
        Me.txtScipsi.MaxLength = 30
        Me.txtScipsi.Name = "txtScipsi"
        Me.txtScipsi.ReadOnly = True
        Me.txtScipsi.Size = New System.Drawing.Size(205, 18)
        Me.txtScipsi.TabIndex = 93
        Me.txtScipsi.TabStop = False
        Me.txtScipsi.Text = "0000000000000000000"
        Me.txtScipsi.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'Label9
        '
        Me.Label9.AutoSize = True
        Me.Label9.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label9.ForeColor = System.Drawing.Color.Black
        Me.Label9.Location = New System.Drawing.Point(377, 22)
        Me.Label9.Name = "Label9"
        Me.Label9.Size = New System.Drawing.Size(41, 17)
        Me.Label9.TabIndex = 98
        Me.Label9.Text = "DISC:"
        '
        'txtVat
        '
        Me.txtVat.BackColor = System.Drawing.Color.SkyBlue
        Me.txtVat.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtVat.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtVat.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtVat.ForeColor = System.Drawing.Color.Navy
        Me.txtVat.Location = New System.Drawing.Point(768, 22)
        Me.txtVat.MaxLength = 30
        Me.txtVat.Name = "txtVat"
        Me.txtVat.ReadOnly = True
        Me.txtVat.Size = New System.Drawing.Size(206, 18)
        Me.txtVat.TabIndex = 95
        Me.txtVat.TabStop = False
        Me.txtVat.Text = "0000000000000000000"
        Me.txtVat.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'txtNet
        '
        Me.txtNet.BackColor = System.Drawing.Color.SkyBlue
        Me.txtNet.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtNet.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtNet.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtNet.ForeColor = System.Drawing.Color.Navy
        Me.txtNet.Location = New System.Drawing.Point(430, 51)
        Me.txtNet.MaxLength = 30
        Me.txtNet.Name = "txtNet"
        Me.txtNet.ReadOnly = True
        Me.txtNet.Size = New System.Drawing.Size(206, 18)
        Me.txtNet.TabIndex = 94
        Me.txtNet.TabStop = False
        Me.txtNet.Text = "0000000000000000000"
        Me.txtNet.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'txtLessppa
        '
        Me.txtLessppa.BackColor = System.Drawing.Color.SkyBlue
        Me.txtLessppa.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtLessppa.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtLessppa.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtLessppa.ForeColor = System.Drawing.Color.Navy
        Me.txtLessppa.Location = New System.Drawing.Point(148, 51)
        Me.txtLessppa.MaxLength = 30
        Me.txtLessppa.Name = "txtLessppa"
        Me.txtLessppa.ReadOnly = True
        Me.txtLessppa.Size = New System.Drawing.Size(206, 18)
        Me.txtLessppa.TabIndex = 92
        Me.txtLessppa.TabStop = False
        Me.txtLessppa.Text = "0000000000000000000"
        Me.txtLessppa.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'txtTotal
        '
        Me.txtTotal.BackColor = System.Drawing.Color.SkyBlue
        Me.txtTotal.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtTotal.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtTotal.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtTotal.ForeColor = System.Drawing.Color.Navy
        Me.txtTotal.Location = New System.Drawing.Point(148, 23)
        Me.txtTotal.MaxLength = 30
        Me.txtTotal.Name = "txtTotal"
        Me.txtTotal.ReadOnly = True
        Me.txtTotal.Size = New System.Drawing.Size(206, 18)
        Me.txtTotal.TabIndex = 91
        Me.txtTotal.TabStop = False
        Me.txtTotal.Text = "0000000000000000000"
        Me.txtTotal.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'Label13
        '
        Me.Label13.AutoSize = True
        Me.Label13.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label13.ForeColor = System.Drawing.Color.Black
        Me.Label13.Location = New System.Drawing.Point(380, 51)
        Me.Label13.Name = "Label13"
        Me.Label13.Size = New System.Drawing.Size(37, 17)
        Me.Label13.TabIndex = 87
        Me.Label13.Text = "NET:"
        '
        'Label12
        '
        Me.Label12.AutoSize = True
        Me.Label12.Font = New System.Drawing.Font("Segoe UI", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label12.ForeColor = System.Drawing.Color.Black
        Me.Label12.Location = New System.Drawing.Point(87, 23)
        Me.Label12.Name = "Label12"
        Me.Label12.Size = New System.Drawing.Size(52, 17)
        Me.Label12.TabIndex = 86
        Me.Label12.Text = "TOTAL:"
        '
        'txtNetTemp
        '
        Me.txtNetTemp.Location = New System.Drawing.Point(478, 498)
        Me.txtNetTemp.Name = "txtNetTemp"
        Me.txtNetTemp.Size = New System.Drawing.Size(100, 20)
        Me.txtNetTemp.TabIndex = 100
        Me.txtNetTemp.Text = "0"
        Me.txtNetTemp.Visible = False
        '
        'txtIndicator
        '
        Me.txtIndicator.Location = New System.Drawing.Point(584, 498)
        Me.txtIndicator.Name = "txtIndicator"
        Me.txtIndicator.Size = New System.Drawing.Size(100, 20)
        Me.txtIndicator.TabIndex = 97
        Me.txtIndicator.Text = "0"
        Me.txtIndicator.Visible = False
        '
        'MenuStrip1
        '
        Me.MenuStrip1.BackColor = System.Drawing.Color.SkyBlue
        Me.MenuStrip1.ImageScalingSize = New System.Drawing.Size(20, 20)
        Me.MenuStrip1.Items.AddRange(New System.Windows.Forms.ToolStripItem() {Me.TransactionToolStripMenuItem1, Me.PrintToolStripMenuItem, Me.SearchToolStripMenuItem, Me.RequestToolStripMenuItem, Me.ToolStripMenuItem1})
        Me.MenuStrip1.Location = New System.Drawing.Point(0, 0)
        Me.MenuStrip1.Name = "MenuStrip1"
        Me.MenuStrip1.Size = New System.Drawing.Size(1028, 24)
        Me.MenuStrip1.TabIndex = 101
        Me.MenuStrip1.Text = "MenuStrip1"
        '
        'TransactionToolStripMenuItem1
        '
        Me.TransactionToolStripMenuItem1.DropDownItems.AddRange(New System.Windows.Forms.ToolStripItem() {Me.BillingStatementToolStripMenuItem1, Me.TransmittalToolStripMenuItem})
        Me.TransactionToolStripMenuItem1.Name = "TransactionToolStripMenuItem1"
        Me.TransactionToolStripMenuItem1.Size = New System.Drawing.Size(85, 20)
        Me.TransactionToolStripMenuItem1.Text = "&Transactions"
        '
        'BillingStatementToolStripMenuItem1
        '
        Me.BillingStatementToolStripMenuItem1.Name = "BillingStatementToolStripMenuItem1"
        Me.BillingStatementToolStripMenuItem1.ShortcutKeys = CType((System.Windows.Forms.Keys.Control Or System.Windows.Forms.Keys.B), System.Windows.Forms.Keys)
        Me.BillingStatementToolStripMenuItem1.Size = New System.Drawing.Size(205, 22)
        Me.BillingStatementToolStripMenuItem1.Text = "Billing Statement"
        '
        'TransmittalToolStripMenuItem
        '
        Me.TransmittalToolStripMenuItem.DropDownItems.AddRange(New System.Windows.Forms.ToolStripItem() {Me.YellowBillsToolStripMenuItem, Me.WhiteBillsToolStripMenuItem})
        Me.TransmittalToolStripMenuItem.Name = "TransmittalToolStripMenuItem"
        Me.TransmittalToolStripMenuItem.Size = New System.Drawing.Size(205, 22)
        Me.TransmittalToolStripMenuItem.Text = "Transmittal"
        '
        'YellowBillsToolStripMenuItem
        '
        Me.YellowBillsToolStripMenuItem.Name = "YellowBillsToolStripMenuItem"
        Me.YellowBillsToolStripMenuItem.Size = New System.Drawing.Size(156, 22)
        Me.YellowBillsToolStripMenuItem.Text = "Yellow Bills"
        '
        'WhiteBillsToolStripMenuItem
        '
        Me.WhiteBillsToolStripMenuItem.Name = "WhiteBillsToolStripMenuItem"
        Me.WhiteBillsToolStripMenuItem.Size = New System.Drawing.Size(156, 22)
        Me.WhiteBillsToolStripMenuItem.Text = "W/ Holding Tax"
        '
        'PrintToolStripMenuItem
        '
        Me.PrintToolStripMenuItem.DropDownItems.AddRange(New System.Windows.Forms.ToolStripItem() {Me.RTCToolStripMenuItem})
        Me.PrintToolStripMenuItem.Name = "PrintToolStripMenuItem"
        Me.PrintToolStripMenuItem.Size = New System.Drawing.Size(44, 20)
        Me.PrintToolStripMenuItem.Text = "&Print"
        '
        'RTCToolStripMenuItem
        '
        Me.RTCToolStripMenuItem.Name = "RTCToolStripMenuItem"
        Me.RTCToolStripMenuItem.Size = New System.Drawing.Size(94, 22)
        Me.RTCToolStripMenuItem.Text = "RTC"
        '
        'SearchToolStripMenuItem
        '
        Me.SearchToolStripMenuItem.DropDownItems.AddRange(New System.Windows.Forms.ToolStripItem() {Me.CustomerToolStripMenuItem, Me.PaidCustomerToolStripMenuItem})
        Me.SearchToolStripMenuItem.Name = "SearchToolStripMenuItem"
        Me.SearchToolStripMenuItem.Size = New System.Drawing.Size(54, 20)
        Me.SearchToolStripMenuItem.Text = "Search"
        '
        'CustomerToolStripMenuItem
        '
        Me.CustomerToolStripMenuItem.Name = "CustomerToolStripMenuItem"
        Me.CustomerToolStripMenuItem.Size = New System.Drawing.Size(152, 22)
        Me.CustomerToolStripMenuItem.Text = "Customer #"
        '
        'PaidCustomerToolStripMenuItem
        '
        Me.PaidCustomerToolStripMenuItem.Name = "PaidCustomerToolStripMenuItem"
        Me.PaidCustomerToolStripMenuItem.Size = New System.Drawing.Size(152, 22)
        Me.PaidCustomerToolStripMenuItem.Text = "Paid Customer"
        '
        'RequestToolStripMenuItem
        '
        Me.RequestToolStripMenuItem.DropDownItems.AddRange(New System.Windows.Forms.ToolStripItem() {Me.BillTransactionsToolStripMenuItem, Me.BackDatesToolStripMenuItem})
        Me.RequestToolStripMenuItem.Name = "RequestToolStripMenuItem"
        Me.RequestToolStripMenuItem.Size = New System.Drawing.Size(61, 20)
        Me.RequestToolStripMenuItem.Text = "Request"
        '
        'BillTransactionsToolStripMenuItem
        '
        Me.BillTransactionsToolStripMenuItem.Name = "BillTransactionsToolStripMenuItem"
        Me.BillTransactionsToolStripMenuItem.Size = New System.Drawing.Size(159, 22)
        Me.BillTransactionsToolStripMenuItem.Text = "Bill Transactions"
        '
        'BackDatesToolStripMenuItem
        '
        Me.BackDatesToolStripMenuItem.Name = "BackDatesToolStripMenuItem"
        Me.BackDatesToolStripMenuItem.Size = New System.Drawing.Size(159, 22)
        Me.BackDatesToolStripMenuItem.Text = "Back Dates"
        '
        'ToolStripMenuItem1
        '
        Me.ToolStripMenuItem1.DropDownItems.AddRange(New System.Windows.Forms.ToolStripItem() {Me.AboutToolStripMenuItem, Me.UpdateSoftwareToolStripMenuItem, Me.ChangePasswordToolStripMenuItem})
        Me.ToolStripMenuItem1.Name = "ToolStripMenuItem1"
        Me.ToolStripMenuItem1.Size = New System.Drawing.Size(44, 20)
        Me.ToolStripMenuItem1.Text = "&Help"
        '
        'AboutToolStripMenuItem
        '
        Me.AboutToolStripMenuItem.Name = "AboutToolStripMenuItem"
        Me.AboutToolStripMenuItem.Size = New System.Drawing.Size(168, 22)
        Me.AboutToolStripMenuItem.Text = "About"
        '
        'UpdateSoftwareToolStripMenuItem
        '
        Me.UpdateSoftwareToolStripMenuItem.Name = "UpdateSoftwareToolStripMenuItem"
        Me.UpdateSoftwareToolStripMenuItem.Size = New System.Drawing.Size(168, 22)
        Me.UpdateSoftwareToolStripMenuItem.Text = "&Update Software"
        '
        'ChangePasswordToolStripMenuItem
        '
        Me.ChangePasswordToolStripMenuItem.Name = "ChangePasswordToolStripMenuItem"
        Me.ChangePasswordToolStripMenuItem.Size = New System.Drawing.Size(168, 22)
        Me.ChangePasswordToolStripMenuItem.Text = "Change Password"
        '
        'SimpleButton1
        '
        Me.SimpleButton1.Appearance.BackColor = System.Drawing.Color.Transparent
        Me.SimpleButton1.Appearance.Font = New System.Drawing.Font("Segoe UI Black", 9.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.SimpleButton1.Appearance.ForeColor = System.Drawing.Color.White
        Me.SimpleButton1.Appearance.Options.UseBackColor = True
        Me.SimpleButton1.Appearance.Options.UseFont = True
        Me.SimpleButton1.Appearance.Options.UseForeColor = True
        Me.SimpleButton1.ButtonStyle = DevExpress.XtraEditors.Controls.BorderStyles.UltraFlat
        Me.SimpleButton1.Location = New System.Drawing.Point(1004, 0)
        Me.SimpleButton1.Name = "SimpleButton1"
        Me.SimpleButton1.Size = New System.Drawing.Size(24, 24)
        Me.SimpleButton1.TabIndex = 103
        Me.SimpleButton1.TabStop = False
        Me.SimpleButton1.Text = "__"
        '
        'forsysdate
        '
        Me.forsysdate.Enabled = True
        Me.forsysdate.Interval = 1000
        '
        'fordatereq
        '
        Me.fordatereq.Enabled = True
        Me.fordatereq.Interval = 1000
        '
        'frmBilling
        '
        Me.AutoScaleDimensions = New System.Drawing.SizeF(6.0!, 13.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.BackColor = System.Drawing.Color.SkyBlue
        Me.BackgroundImageLayout = System.Windows.Forms.ImageLayout.Stretch
        Me.ClientSize = New System.Drawing.Size(1028, 611)
        Me.Controls.Add(Me.SimpleButton1)
        Me.Controls.Add(Me.txtNetTemp)
        Me.Controls.Add(Me.GroupBox4)
        Me.Controls.Add(Me.GroupBox2)
        Me.Controls.Add(Me.GroupBox1)
        Me.Controls.Add(Me.GroupBox3)
        Me.Controls.Add(Me.txtIndicator)
        Me.Controls.Add(Me.MenuStrip1)
        Me.FormBorderStyle = System.Windows.Forms.FormBorderStyle.None
        Me.Icon = CType(resources.GetObject("$this.Icon"), System.Drawing.Icon)
        Me.MainMenuStrip = Me.MenuStrip1
        Me.MaximizeBox = False
        Me.Name = "frmBilling"
        Me.StartPosition = System.Windows.Forms.FormStartPosition.CenterScreen
        Me.Text = "South Cotabato Integrated Port Services Inc. Billing System v2.0"
        Me.GroupBox1.ResumeLayout(False)
        Me.GroupBox5.ResumeLayout(False)
        Me.GroupBox5.PerformLayout()
        CType(Me.PictureBox1, System.ComponentModel.ISupportInitialize).EndInit()
        Me.GroupBox2.ResumeLayout(False)
        Me.GroupBox2.PerformLayout()
        CType(Me.lbldatetrans.Properties.CalendarTimeProperties, System.ComponentModel.ISupportInitialize).EndInit()
        CType(Me.lbldatetrans.Properties, System.ComponentModel.ISupportInitialize).EndInit()
        Me.GroupBox3.ResumeLayout(False)
        CType(Me.dgItems, System.ComponentModel.ISupportInitialize).EndInit()
        Me.GroupBox4.ResumeLayout(False)
        Me.GroupBox4.PerformLayout()
        Me.MenuStrip1.ResumeLayout(False)
        Me.MenuStrip1.PerformLayout()
        Me.ResumeLayout(False)
        Me.PerformLayout()

    End Sub
    Friend WithEvents GroupBox1 As System.Windows.Forms.GroupBox
    Friend WithEvents btnPrint As System.Windows.Forms.Button
    Friend WithEvents btnSearch As System.Windows.Forms.Button
    Friend WithEvents btnOut As System.Windows.Forms.Button
    Friend WithEvents GroupBox2 As System.Windows.Forms.GroupBox
    Friend WithEvents Label11 As System.Windows.Forms.Label
    Friend WithEvents cbRoute As System.Windows.Forms.ComboBox
    Friend WithEvents Label8 As System.Windows.Forms.Label
    Friend WithEvents Label7 As System.Windows.Forms.Label
    Friend WithEvents Label6 As System.Windows.Forms.Label
    Friend WithEvents lblAccname As System.Windows.Forms.Label
    Friend WithEvents Label4 As System.Windows.Forms.Label
    Friend WithEvents txtTime As System.Windows.Forms.Label
    Friend WithEvents Label1 As System.Windows.Forms.Label
    Friend WithEvents GroupBox3 As System.Windows.Forms.GroupBox
    Friend WithEvents btnDeleteItem As System.Windows.Forms.Button
    Friend WithEvents btnAddItem As System.Windows.Forms.Button
    Friend WithEvents btnSave As System.Windows.Forms.Button
    Friend WithEvents GroupBox4 As System.Windows.Forms.GroupBox
    Friend WithEvents txtVat As System.Windows.Forms.TextBox
    Friend WithEvents txtNet As System.Windows.Forms.TextBox
    Friend WithEvents txtScipsi As System.Windows.Forms.TextBox
    Friend WithEvents txtLessppa As System.Windows.Forms.TextBox
    Friend WithEvents txtTotal As System.Windows.Forms.TextBox
    Friend WithEvents Label16 As System.Windows.Forms.Label
    Friend WithEvents Label15 As System.Windows.Forms.Label
    Friend WithEvents Label13 As System.Windows.Forms.Label
    Friend WithEvents Label12 As System.Windows.Forms.Label
    Friend WithEvents txtAccountno As System.Windows.Forms.MaskedTextBox
    Friend WithEvents txtBillno As System.Windows.Forms.MaskedTextBox
    Friend WithEvents txtVoyage As System.Windows.Forms.MaskedTextBox
    Friend WithEvents cbType As System.Windows.Forms.ComboBox
    Friend WithEvents Label2 As System.Windows.Forms.Label
    Friend WithEvents vat As System.Windows.Forms.CheckBox
    Friend WithEvents ppa As System.Windows.Forms.CheckBox
    Friend WithEvents txtVessel As System.Windows.Forms.TextBox
    Friend WithEvents GroupBox5 As System.Windows.Forms.GroupBox
    Friend WithEvents txtNotice As System.Windows.Forms.TextBox
    Friend WithEvents PictureBox1 As System.Windows.Forms.PictureBox
    Friend WithEvents btnReport As System.Windows.Forms.Button
    Friend WithEvents btnOr As System.Windows.Forms.Button
    Friend WithEvents txtIndicator As System.Windows.Forms.TextBox
    Friend WithEvents txtTempno As System.Windows.Forms.TextBox
    Friend WithEvents txtSearchIndicator As System.Windows.Forms.TextBox
    Friend WithEvents Label3 As System.Windows.Forms.Label
    Friend WithEvents txtName As System.Windows.Forms.TextBox
    Friend WithEvents labelstatus As System.Windows.Forms.TextBox
    Friend WithEvents Label5 As System.Windows.Forms.Label
    Friend WithEvents txtDiscount As System.Windows.Forms.TextBox
    Friend WithEvents Label9 As System.Windows.Forms.Label
    Friend WithEvents Label10 As System.Windows.Forms.Label
    Friend WithEvents txtParticulars As System.Windows.Forms.TextBox
    Friend WithEvents txtNetTemp As System.Windows.Forms.TextBox
    Friend WithEvents txtnametemp As System.Windows.Forms.TextBox
    Friend WithEvents btnUpdate As System.Windows.Forms.Button
    Friend WithEvents btnEdit As System.Windows.Forms.Button
    Friend WithEvents Label18 As System.Windows.Forms.Label
    Friend WithEvents MenuStrip1 As System.Windows.Forms.MenuStrip
    Friend WithEvents ToolStripMenuItem1 As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents TransactionToolStripMenuItem1 As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents BillingStatementToolStripMenuItem1 As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents MaskedTextBox1 As System.Windows.Forms.MaskedTextBox
    Friend WithEvents Label14 As System.Windows.Forms.Label
    Friend WithEvents PrintToolStripMenuItem As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents RTCToolStripMenuItem As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents UpdateSoftwareToolStripMenuItem As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents TransmittalToolStripMenuItem As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents YellowBillsToolStripMenuItem As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents WhiteBillsToolStripMenuItem As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents AboutToolStripMenuItem As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents dgItems As System.Windows.Forms.DataGridView
    Friend WithEvents Column8 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents DataGridViewTextBoxColumn1 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents DataGridViewTextBoxColumn2 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column3 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column5 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column6 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column7 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column9 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column4 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column10 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column11 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column12 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents lbldatetrans As DevExpress.XtraEditors.DateEdit
    Friend WithEvents btnNewBill As System.Windows.Forms.Button
    Friend WithEvents SearchToolStripMenuItem As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents CustomerToolStripMenuItem As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents SimpleButton1 As DevExpress.XtraEditors.SimpleButton
    Friend WithEvents txtperiod As System.Windows.Forms.TextBox
    Friend WithEvents PaidCustomerToolStripMenuItem As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents forsysdate As System.Windows.Forms.Timer
    Friend WithEvents RequestToolStripMenuItem As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents BillTransactionsToolStripMenuItem As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents BackDatesToolStripMenuItem As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents fordatereq As System.Windows.Forms.Timer
    Friend WithEvents ChangePasswordToolStripMenuItem As System.Windows.Forms.ToolStripMenuItem
    Friend WithEvents lblAddress As DevExpress.XtraEditors.LabelControl
    Friend WithEvents lblTin As Label
    Friend WithEvents Label17 As Label
    Friend WithEvents txtBstyle As TextBox
End Class
